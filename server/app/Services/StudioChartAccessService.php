<?php

namespace App\Services;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\Person;
use App\Support\PersonInstrumentPartMatcher;
use App\Support\StudioLibraryAvailability;
use Illuminate\Support\Collection;

class StudioChartAccessService
{
    public function __construct(
        private readonly PersonInstrumentPartMatcher $matcher,
        private readonly StudioLibraryAvailability $library,
    ) {}

    public function libraryIsAvailable(): bool
    {
        return $this->library->isAvailable();
    }

    /**
     * @return list<int>
     */
    public function matchingInstrumentPartIds(Person $person): array
    {
        if (! $this->library->isAvailable()) {
            return [];
        }

        return $this->matcher->matchingInstrumentPartIds($person, (int) config('portal.band_id', 1));
    }

    /**
     * @return Collection<int, Song>
     */
    public function songsForPerson(Person $person): Collection
    {
        if (! $this->library->isAvailable()) {
            return collect();
        }

        $partIds = $this->matchingInstrumentPartIds($person);

        if ($partIds === []) {
            return collect();
        }

        return Song::query()
            ->where('band_id', (int) config('portal.band_id', 1))
            ->whereHas('songInstrumentParts', function ($query) use ($partIds): void {
                $query->whereIn('instrument_part_id', $partIds)
                    ->whereNotNull('chart_id');
            })
            ->withCount(['songInstrumentParts as my_chart_count' => function ($query) use ($partIds): void {
                $query->whereIn('instrument_part_id', $partIds)
                    ->whereNotNull('chart_id');
            }])
            ->orderBy('name')
            ->get();
    }

    public function songCountForPerson(Person $person): int
    {
        return $this->songsForPerson($person)->count();
    }

    public function chartCountForPerson(Person $person): int
    {
        return (int) $this->songsForPerson($person)->sum('my_chart_count');
    }

    /**
     * @return list<string>
     */
    public function partNamesForPersonAndSong(Person $person, Song $song): array
    {
        if (! $this->library->isAvailable()) {
            return [];
        }

        $partIds = $this->matchingInstrumentPartIds($person);

        if ($partIds === []) {
            return [];
        }

        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);

        return SongInstrumentPart::query()
            ->where('song_id', $song->id)
            ->whereIn('instrument_part_id', $partIds)
            ->whereNotNull('chart_id')
            ->with('instrumentPart')
            ->get()
            ->map(fn (SongInstrumentPart $link) => $link->instrumentPart?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, SongInstrumentPart>
     */
    public function chartLinksForPersonAndSong(Person $person, Song $song): Collection
    {
        if (! $this->library->isAvailable()) {
            return collect();
        }

        $partIds = $this->matchingInstrumentPartIds($person);

        if ($partIds === []) {
            return collect();
        }

        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);

        return SongInstrumentPart::query()
            ->where('song_id', $song->id)
            ->whereIn('instrument_part_id', $partIds)
            ->whereNotNull('chart_id')
            ->with(['instrumentPart', 'chart'])
            ->get()
            ->sortBy(fn (SongInstrumentPart $link) => $link->instrumentPart?->name ?? '')
            ->values();
    }

    public function personCanAccessChart(Person $person, Chart $chart): bool
    {
        if (! $this->library->isAvailable()) {
            return false;
        }

        $partIds = $this->matchingInstrumentPartIds($person);

        if ($partIds === []) {
            return false;
        }

        $chart->loadMissing('song');

        if ($chart->song === null || $chart->song->band_id !== (int) config('portal.band_id', 1)) {
            return false;
        }

        return SongInstrumentPart::query()
            ->where('song_id', $chart->song_id)
            ->where('chart_id', $chart->id)
            ->whereIn('instrument_part_id', $partIds)
            ->exists();
    }
}
