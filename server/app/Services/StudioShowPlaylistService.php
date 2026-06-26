<?php

namespace App\Services;

use App\Models\Library\Song;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Support\StudioLibraryAvailability;
use App\Support\StudioSongMetadata;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudioShowPlaylistService
{
    public function __construct(
        private readonly StudioShowService $shows,
        private readonly StudioSongMetadata $songMetadata,
        private readonly StudioLibraryAvailability $library,
    ) {}

    /**
     * @return Collection<int, array{
     *     item: ShowPlaylistItem,
     *     metadata: array<string, mixed>,
     *     instrument_parts: list<array{name: string, has_chart: bool}>,
     * }>
     */
    public function playlistEntriesForShow(int $showId, ?int $bandId = null): Collection
    {
        $show = $this->shows->showForPortal($showId, $bandId);

        if (! $this->library->isAvailable()) {
            return collect();
        }

        $items = ShowPlaylistItem::query()
            ->where('show_id', $show->id)
            ->active()
            ->orderBy('position')
            ->get();

        $songIds = $items->pluck('song_id')->all();
        $songs = Song::query()
            ->with([
                'timeSignature',
                'musicalKey',
                'mood',
                'songInstrumentParts.instrumentPart',
                'songInstrumentParts.chart',
            ])
            ->whereIn('id', $songIds)
            ->get()
            ->keyBy('id');

        return $items->map(function (ShowPlaylistItem $item) use ($songs): array {
            /** @var Song|null $song */
            $song = $songs->get($item->song_id);

            if ($song !== null) {
                $item->setRelation('song', $song);
            }

            return [
                'item' => $item,
                'metadata' => $song ? $this->songMetadata->forSong($song) : [
                    'bpm' => null,
                    'time_signature' => null,
                    'musical_key' => null,
                    'mood_label' => StudioSongMetadata::DEFAULT_MOOD_LABEL,
                    'has_metadata' => false,
                ],
                'instrument_parts' => $song ? $this->instrumentPartsForSong($song) : [],
            ];
        });
    }

    /**
     * @return Collection<int, Song>
     */
    public function selectableSongsForShow(int $showId, ?int $bandId = null): Collection
    {
        $bandId ??= (int) config('portal.band_id', 1);
        $show = $this->shows->showForPortal($showId, $bandId);

        if (! $this->library->isAvailable()) {
            return collect();
        }

        $activeSongIds = ShowPlaylistItem::query()
            ->where('show_id', $show->id)
            ->active()
            ->pluck('song_id');

        return Song::query()
            ->where('band_id', $bandId)
            ->whereNotIn('id', $activeSongIds)
            ->orderBy('name')
            ->get();
    }

    public function addSongToPlaylist(Show $show, int $songId, ?int $bandId = null): ShowPlaylistItem
    {
        $bandId ??= (int) config('portal.band_id', 1);
        $portalShow = $this->shows->showForPortal($show->id, $bandId);

        $song = Song::query()
            ->where('band_id', $bandId)
            ->findOrFail($songId);

        $existing = ShowPlaylistItem::query()
            ->where('show_id', $portalShow->id)
            ->where('song_id', $song->id)
            ->first();

        if ($existing !== null && $existing->is_active) {
            throw new InvalidArgumentException('Song is already on this playlist.');
        }

        if ($existing !== null) {
            $existing->update([
                'is_active' => true,
                'position' => $this->nextPosition($portalShow->id),
            ]);

            return $existing->fresh();
        }

        return ShowPlaylistItem::query()->create([
            'show_id' => $portalShow->id,
            'song_id' => $song->id,
            'position' => $this->nextPosition($portalShow->id),
            'is_active' => true,
        ]);
    }

    public function archivePlaylistItem(ShowPlaylistItem $item, ?int $bandId = null): ShowPlaylistItem
    {
        $portalItem = $this->playlistItemForPortal($item->id, $bandId);

        if ($portalItem->is_active) {
            $portalItem->update(['is_active' => false]);
            $this->renormalizeActivePositions($portalItem->show_id);
        }

        return $portalItem->fresh();
    }

    public function updatePlaylistItemNotes(ShowPlaylistItem $item, ?string $notes, ?int $bandId = null): ShowPlaylistItem
    {
        $portalItem = $this->playlistItemForPortal($item->id, $bandId);
        $portalItem->update([
            'notes' => $this->normalizeNullableString($notes),
        ]);

        return $portalItem->fresh();
    }

    public function movePlaylistItemUp(ShowPlaylistItem $item, ?int $bandId = null): void
    {
        $portalItem = $this->playlistItemForPortal($item->id, $bandId);
        $orderedIds = $this->activeOrderedItemIds($portalItem->show_id);
        $index = array_search($portalItem->id, $orderedIds, true);

        if ($index === false || $index === 0) {
            return;
        }

        [$orderedIds[$index - 1], $orderedIds[$index]] = [$orderedIds[$index], $orderedIds[$index - 1]];
        $this->applyOrder($portalItem->show_id, $orderedIds);
    }

    public function movePlaylistItemDown(ShowPlaylistItem $item, ?int $bandId = null): void
    {
        $portalItem = $this->playlistItemForPortal($item->id, $bandId);
        $orderedIds = $this->activeOrderedItemIds($portalItem->show_id);
        $index = array_search($portalItem->id, $orderedIds, true);

        if ($index === false || $index === count($orderedIds) - 1) {
            return;
        }

        [$orderedIds[$index + 1], $orderedIds[$index]] = [$orderedIds[$index], $orderedIds[$index + 1]];
        $this->applyOrder($portalItem->show_id, $orderedIds);
    }

    public function playlistItemForPortal(int $itemId, ?int $bandId = null): ShowPlaylistItem
    {
        $bandId ??= (int) config('portal.band_id', 1);

        return ShowPlaylistItem::query()
            ->whereHas('show', fn ($query) => $query->where('band_id', $bandId))
            ->findOrFail($itemId);
    }

    /**
     * @return list<array{name: string, has_chart: bool}>
     */
    private function instrumentPartsForSong(Song $song): array
    {
        return $song->songInstrumentParts
            ->sortBy(fn ($row) => $row->instrumentPart?->name ?? '')
            ->map(fn ($row): array => [
                'name' => $row->instrumentPart?->name ?? 'Instrument part',
                'has_chart' => $row->chart_id !== null,
            ])
            ->values()
            ->all();
    }

    private function nextPosition(int $showId): int
    {
        $max = (int) ShowPlaylistItem::query()
            ->where('show_id', $showId)
            ->max('position');

        return $max + 1;
    }

    /**
     * @return list<int>
     */
    private function activeOrderedItemIds(int $showId): array
    {
        return ShowPlaylistItem::query()
            ->where('show_id', $showId)
            ->active()
            ->orderBy('position')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function renormalizeActivePositions(int $showId): void
    {
        $this->applyOrder($showId, $this->activeOrderedItemIds($showId));
    }

    /**
     * @param  list<int>  $orderedItemIds
     */
    private function applyOrder(int $showId, array $orderedItemIds): void
    {
        DB::transaction(function () use ($showId, $orderedItemIds): void {
            foreach ($orderedItemIds as $index => $itemId) {
                ShowPlaylistItem::query()
                    ->where('id', $itemId)
                    ->where('show_id', $showId)
                    ->update(['position' => 1000 + $index + 1]);
            }

            foreach ($orderedItemIds as $index => $itemId) {
                ShowPlaylistItem::query()
                    ->where('id', $itemId)
                    ->where('show_id', $showId)
                    ->update(['position' => $index + 1]);
            }
        });
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
