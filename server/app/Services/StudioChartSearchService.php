<?php

namespace App\Services;

use App\Data\StudioChartSearchResult;
use App\Models\Library\Song;
use App\Models\Person;
use Illuminate\Support\Collection;

class StudioChartSearchService
{
    private const DEFAULT_LIMIT = 8;

    public function __construct(
        private readonly StudioChartAccessService $chartAccess,
    ) {}

    /**
     * Search accessible songs for a musician.
     *
     * Filter order: accessible songs → query match → limited results.
     * Matching is case-insensitive with partial and word-token support.
     * Future enhancements (aliases, trigram, Soundex) can extend {@see matchesQuery()}.
     *
     * @return list<StudioChartSearchResult>
     */
    public function search(Person $person, string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $accessible = $this->accessibleSongsWithParts($person);

        return $accessible
            ->filter(fn (array $entry) => $this->matchesQuery($entry['song']->name, $query))
            ->take(max(1, $limit))
            ->map(fn (array $entry) => new StudioChartSearchResult(
                songId: (int) $entry['song']->id,
                name: (string) $entry['song']->name,
                url: route('studio.charts.show', $entry['song']),
                parts: $entry['parts'],
            ))
            ->values()
            ->map(fn (StudioChartSearchResult $result) => $result->toArray())
            ->all();
    }

    /**
     * @return Collection<int, array{song: Song, parts: list<string>}>
     */
    private function accessibleSongsWithParts(Person $person): Collection
    {
        $partIds = $this->chartAccess->matchingInstrumentPartIds($person);

        if ($partIds === []) {
            return collect();
        }

        return $this->chartAccess->songsForPerson($person)
            ->map(function (Song $song) use ($person): array {
                return [
                    'song' => $song,
                    'parts' => $this->chartAccess->partNamesForPersonAndSong($person, $song),
                ];
            });
    }

    /**
     * Case-insensitive partial and word-token matching.
     */
    public function matchesQuery(string $songName, string $query): bool
    {
        $query = mb_strtolower(trim($query));
        $haystack = mb_strtolower(trim($songName));

        if ($query === '' || $haystack === '') {
            return false;
        }

        if (str_contains($haystack, $query)) {
            return true;
        }

        $queryTokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $nameTokens = preg_split('/\s+/u', $haystack, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($queryTokens as $queryToken) {
            $tokenMatched = false;

            foreach ($nameTokens as $nameToken) {
                if (str_starts_with($nameToken, $queryToken) || str_contains($nameToken, $queryToken)) {
                    $tokenMatched = true;
                    break;
                }
            }

            if (! $tokenMatched && ! str_contains($haystack, $queryToken)) {
                return false;
            }
        }

        return $queryTokens !== [];
    }
}
