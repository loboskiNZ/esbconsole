<?php

namespace App\Services;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Models\ShowPlaylistItem;
use App\Support\StudioLibraryAvailability;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudioSongLibraryService
{
    public function __construct(
        private readonly StudioLibraryAvailability $library,
        private readonly StudioChartSearchService $chartSearch,
        private readonly SongCodeAllocator $songCodes,
    ) {}

    /**
     * @return array{
     *     song_count: int,
     *     archived_count: int,
     *     chart_count: int,
     *     song_asset_count: int,
     * }
     */
    public function summaryForBand(?int $bandId = null): array
    {
        $bandId ??= (int) config('portal.band_id', 1);

        if (! $this->library->isAvailable()) {
            return [
                'song_count' => 0,
                'archived_count' => 0,
                'chart_count' => 0,
                'song_asset_count' => 0,
            ];
        }

        $activeSongIds = Song::query()
            ->where('band_id', $bandId)
            ->active()
            ->pluck('id');

        return [
            'song_count' => $activeSongIds->count(),
            'archived_count' => Song::query()->where('band_id', $bandId)->archived()->count(),
            'chart_count' => Chart::query()->whereIn('song_id', $activeSongIds)->count(),
            'song_asset_count' => SongAsset::query()->whereIn('song_id', $activeSongIds)->count(),
        ];
    }

    /**
     * @return list<string>
     */
    public function genreOptionsForBand(?int $bandId = null): array
    {
        $bandId ??= (int) config('portal.band_id', 1);

        if (! $this->library->isAvailable()) {
            return [];
        }

        return Song::query()
            ->where('band_id', $bandId)
            ->active()
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->distinct()
            ->orderBy('genre')
            ->pluck('genre')
            ->map(fn ($genre) => (string) $genre)
            ->all();
    }

    /**
     * @return EloquentCollection<int, Song>
     */
    public function songsForLibrary(
        ?int $bandId = null,
        bool $showArchived = false,
        ?string $query = null,
        ?string $genre = null,
    ): EloquentCollection {
        $bandId ??= (int) config('portal.band_id', 1);

        if (! $this->library->isAvailable()) {
            return new EloquentCollection;
        }

        $builder = Song::query()
            ->with([
                'musicalKey',
                'timeSignature',
                'mood',
                'songInstrumentParts.instrumentPart',
                'charts',
                'assets',
            ])
            ->where('band_id', $bandId)
            ->orderBy('song_code')
            ->orderBy('name');

        if ($showArchived) {
            $builder->archived();
        } else {
            $builder->active();
        }

        if ($genre !== null && $genre !== '') {
            $builder->where('genre', $genre);
        }

        $songs = $builder->get();

        $query = trim((string) $query);
        if ($query === '') {
            return $songs;
        }

        return $songs
            ->filter(fn (Song $song): bool => $this->songMatchesSearch($song, $query))
            ->values();
    }

    /**
     * @param  list<int>  $songIds
     * @return array<int, list<string>>
     */
    public function showNamesForSongs(array $songIds): array
    {
        if ($songIds === [] || ! $this->library->isAvailable()) {
            return [];
        }

        /** @var array<int, list<string>> $map */
        $map = [];

        ShowPlaylistItem::query()
            ->whereIn('song_id', $songIds)
            ->active()
            ->with('show:id,name')
            ->get()
            ->each(function (ShowPlaylistItem $item) use (&$map): void {
                if ($item->show === null) {
                    return;
                }

                $songId = (int) $item->song_id;
                $name = (string) $item->show->name;

                if (! in_array($name, $map[$songId] ?? [], true)) {
                    $map[$songId][] = $name;
                }
            });

        foreach ($map as $songId => $names) {
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            $map[$songId] = $names;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createSong(array $attributes, ?int $bandId = null): Song
    {
        $bandId ??= (int) config('portal.band_id', 1);

        abort_unless($this->library->isAvailable(), 503);

        $payload = [
            'public_id' => (string) Str::uuid(),
            'band_id' => $bandId,
            'song_code' => $this->songCodes->nextForBand($bandId),
            'name' => (string) $attributes['name'],
            'status' => Song::STATUS_DRAFT,
        ];

        foreach (['bpm', 'musical_key_id', 'director_notes', 'spotify_url', 'youtube_url'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if ($this->hasDurationColumn() && array_key_exists('duration_seconds', $attributes)) {
            $payload['duration_seconds'] = $attributes['duration_seconds'];
        }

        return Song::query()->create($payload);
    }

    public function archiveSong(Song $song, ?int $bandId = null): Song
    {
        $this->ensureSongBelongsToBand($song, $bandId);

        if ($song->isArchived()) {
            return $song;
        }

        $song->update(['status' => Song::STATUS_ARCHIVED]);

        return $song->fresh();
    }

    public function restoreSong(Song $song, ?int $bandId = null): Song
    {
        $this->ensureSongBelongsToBand($song, $bandId);

        if (! $song->isArchived()) {
            return $song;
        }

        $song->update(['status' => Song::STATUS_DRAFT]);

        return $song->fresh();
    }

    public function formatDurationLabel(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remaining);
        }

        return sprintf('%d:%02d', $minutes, $remaining);
    }

    public function parseDurationInput(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return max(0, (int) $value);
        }

        $parts = array_map('intval', explode(':', $value));

        return match (count($parts)) {
            2 => ($parts[0] * 60) + $parts[1],
            3 => ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2],
            default => null,
        };
    }

    public function hasDurationColumn(): bool
    {
        $connection = config('portal.library_connection');

        return is_string($connection)
            && Schema::connection($connection)->hasColumn('songs', 'duration_seconds');
    }

    private function ensureSongBelongsToBand(Song $song, ?int $bandId = null): void
    {
        $bandId ??= (int) config('portal.band_id', 1);

        if ((int) $song->band_id !== $bandId) {
            throw new InvalidArgumentException('Song does not belong to this band.');
        }
    }

    private function songMatchesSearch(Song $song, string $query): bool
    {
        foreach ($this->searchableSongFields($song) as $field) {
            if ($this->chartSearch->matchesQuery($field, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function searchableSongFields(Song $song): array
    {
        return array_values(array_filter([
            (string) $song->song_code,
            (string) $song->name,
            filled($song->reference_title) ? (string) $song->reference_title : null,
            filled($song->genre) ? (string) $song->genre : null,
            filled($song->spotify_url) ? (string) $song->spotify_url : null,
            filled($song->youtube_url) ? (string) $song->youtube_url : null,
        ], static fn (?string $value): bool => filled($value)));
    }
}
