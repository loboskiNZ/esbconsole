<?php

namespace App\Services;

use App\Models\Library\Song;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\User;
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
        private readonly StudioChartAccessService $chartAccess,
    ) {}

    /**
     * @return array{
     *     entries: Collection<int, array{
     *         item: ShowPlaylistItem,
     *         metadata: array<string, mixed>,
     *         instrument_parts: list<array{
     *             song_id: int,
     *             song_instrument_part_id: int,
     *             instrument_part_id: int|null,
     *             chart_id: int|null,
     *             name: string,
     *             has_chart: bool,
     *             chart_status_label: string,
     *         }>,
     *         required_part_count: int,
     *     }>,
     *     summary: array{
     *         song_count: int,
     *         instrument_part_count: int,
     *         charts_available: int,
     *         charts_missing: int,
     *     },
     *     show_instrument_parts: list<array{name: string}>,
     * }
     */
    public function playlistViewForShow(int $showId, ?int $bandId = null, ?User $viewer = null): array
    {
        $isDirector = $viewer?->isDirector() ?? false;
        $entries = $this->playlistEntriesForShow($showId, $bandId, $viewer, $isDirector);

        $chartsAvailable = 0;
        $chartsMissing = 0;
        /** @var array<string, string> $distinctParts */
        $distinctParts = [];

        foreach ($entries as $entry) {
            foreach ($entry['instrument_parts'] as $part) {
                if ($part['has_chart']) {
                    $chartsAvailable++;
                } else {
                    $chartsMissing++;
                }

                $key = mb_strtolower(trim($part['name']));
                $distinctParts[$key] = $part['name'];
            }
        }

        $showInstrumentParts = collect($distinctParts)
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (string $name): array => ['name' => $name])
            ->values()
            ->all();

        return [
            'entries' => $entries,
            'summary' => [
                'song_count' => $entries->count(),
                'instrument_part_count' => count($distinctParts),
                'charts_available' => $chartsAvailable,
                'charts_missing' => $chartsMissing,
            ],
            'show_instrument_parts' => $showInstrumentParts,
        ];
    }

    /**
     * @return Collection<int, array{
     *     item: ShowPlaylistItem,
     *     metadata: array<string, mixed>,
     *     instrument_parts: list<array{
     *         song_id: int,
     *         song_instrument_part_id: int,
     *         instrument_part_id: int|null,
     *         chart_id: int|null,
     *         name: string,
     *         has_chart: bool,
     *         chart_status_label: string,
     *     }>,
     *     required_part_count: int,
     * }>
     */
    public function playlistEntriesForShow(
        int $showId,
        ?int $bandId = null,
        ?User $viewer = null,
        ?bool $isDirector = null,
    ): Collection {
        $isDirector ??= $viewer?->isDirector() ?? false;
        $show = $this->shows->showForPortal($showId, $bandId);

        if (! $this->library->isAvailable()) {
            return collect();
        }

        $items = ShowPlaylistItem::query()
            ->where('show_id', $show->id)
            ->active()
            ->orderBy('position')
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $songs = $this->loadSongsForPlaylist($items->pluck('song_id')->all());

        return $items->map(function (ShowPlaylistItem $item) use ($songs, $viewer, $isDirector): array {
            /** @var Song|null $song */
            $song = $songs->get($item->song_id);

            if ($song !== null) {
                $item->setRelation('song', $song);
            }

            $instrumentParts = $song ? $this->instrumentPartsForSong($song) : [];
            $instrumentParts = $this->filterInstrumentPartsForViewer($instrumentParts, $viewer, $isDirector);

            return [
                'item' => $item,
                'metadata' => $song ? $this->songMetadata->forSong($song) : [
                    'bpm' => null,
                    'time_signature' => null,
                    'musical_key' => null,
                    'mood_label' => StudioSongMetadata::DEFAULT_MOOD_LABEL,
                    'has_metadata' => false,
                ],
                'instrument_parts' => $instrumentParts,
                'required_part_count' => count($instrumentParts),
            ];
        });
    }

    /**
     * @param  list<int>  $songIds
     * @return Collection<int, Song>
     */
    private function loadSongsForPlaylist(array $songIds): Collection
    {
        if ($songIds === []) {
            return collect();
        }

        return Song::query()
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

    /**
     * @return array<int, int>
     */
    public function reorderPlaylistItems(Show $show, array $orderedItemIds, ?int $bandId = null): array
    {
        $bandId ??= (int) config('portal.band_id', 1);
        $portalShow = $this->shows->showForPortal($show->id, $bandId);
        $activeIds = $this->activeOrderedItemIds($portalShow->id);

        $orderedItemIds = array_values(array_map(static fn ($id): int => (int) $id, $orderedItemIds));

        if (count($orderedItemIds) !== count($activeIds)) {
            throw new InvalidArgumentException('Playlist order must include each active song once.');
        }

        $expected = $activeIds;
        sort($expected);
        $received = $orderedItemIds;
        sort($received);

        if ($expected !== $received) {
            throw new InvalidArgumentException('Playlist order must include each active song once.');
        }

        $this->applyOrder($portalShow->id, $orderedItemIds);

        $positions = [];

        foreach ($orderedItemIds as $index => $itemId) {
            $positions[$itemId] = $index + 1;
        }

        return $positions;
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
     * @return list<array{
     *     song_id: int,
     *     song_instrument_part_id: int,
     *     instrument_part_id: int|null,
     *     chart_id: int|null,
     *     name: string,
     *     has_chart: bool,
     *     chart_status_label: string,
     * }>
     */
    private function instrumentPartsForSong(Song $song): array
    {
        return $song->songInstrumentParts
            ->sortBy(fn ($row) => $row->instrumentPart?->name ?? '')
            ->map(function ($row) use ($song): array {
                $name = $row->instrumentPart?->name ?? 'Instrument part';
                $hasChart = $row->chart_id !== null;

                return [
                    'song_id' => $song->id,
                    'song_instrument_part_id' => $row->id,
                    'instrument_part_id' => $row->instrument_part_id,
                    'chart_id' => $row->chart_id,
                    'name' => $name,
                    'has_chart' => $hasChart,
                    'chart_status_label' => $hasChart ? 'chart available' : 'chart missing',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{
     *     song_id: int,
     *     song_instrument_part_id: int,
     *     instrument_part_id: int|null,
     *     chart_id: int|null,
     *     name: string,
     *     has_chart: bool,
     *     chart_status_label: string,
     * }>  $parts
     * @return list<array{
     *     song_id: int,
     *     song_instrument_part_id: int,
     *     instrument_part_id: int|null,
     *     chart_id: int|null,
     *     name: string,
     *     has_chart: bool,
     *     chart_status_label: string,
     * }>
     */
    private function filterInstrumentPartsForViewer(array $parts, ?User $viewer, bool $isDirector): array
    {
        if ($isDirector || $viewer === null) {
            return $parts;
        }

        $viewer->loadMissing('person.instruments');
        $person = $viewer->person;

        if ($person === null) {
            return [];
        }

        $allowedPartIds = $this->chartAccess->matchingInstrumentPartIds($person);

        if ($allowedPartIds === []) {
            return [];
        }

        return array_values(array_filter(
            $parts,
            fn (array $part): bool => $part['instrument_part_id'] !== null
                && in_array($part['instrument_part_id'], $allowedPartIds, true),
        ));
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
