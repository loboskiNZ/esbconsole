<?php

namespace App\Services;

use App\DataTransferObjects\BulkSongCreationResult;
use App\Models\Band;
use App\Models\InstrumentPart;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use Illuminate\Support\Facades\DB;

class BulkSongCreationService
{
    public function __construct(
        private readonly SongCodeAllocator $songCodeAllocator,
    ) {}

    /**
     * @param  array<int, int>  $instrumentPartIds
     */
    public function create(Band $band, string $songNamesText, array $instrumentPartIds): BulkSongCreationResult
    {
        $names = $this->parseSongNames($songNamesText);
        $instrumentParts = $this->resolveInstrumentParts($band, $instrumentPartIds);

        $existingNames = $band->songs()->pluck('name')->all();
        $seenInSubmission = [];

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($band, $names, $instrumentParts, $existingNames, &$seenInSubmission, &$created, &$skipped) {
            foreach ($names as $name) {
                if (isset($seenInSubmission[$name])) {
                    $skipped[] = [
                        'name' => $name,
                        'reason' => 'Duplicate in submitted list.',
                    ];

                    continue;
                }

                $seenInSubmission[$name] = true;

                if (in_array($name, $existingNames, true)) {
                    $skipped[] = [
                        'name' => $name,
                        'reason' => 'Song already exists for this band.',
                    ];

                    continue;
                }

                $songCode = $this->songCodeAllocator->nextForBand($band);

                $song = Song::create([
                    'band_id' => $band->id,
                    'song_code' => $songCode,
                    'name' => $name,
                    'status' => Song::STATUS_DRAFT,
                ]);

                foreach ($instrumentParts as $instrumentPart) {
                    SongInstrumentPart::create([
                        'song_id' => $song->id,
                        'instrument_part_id' => $instrumentPart->id,
                    ]);
                }

                $existingNames[] = $name;

                $created[] = [
                    'name' => $song->name,
                    'song_code' => $song->song_code,
                    'song_id' => $song->id,
                ];
            }
        });

        return new BulkSongCreationResult($created, $skipped);
    }

    /**
     * @return array<int, string>
     */
    private function parseSongNames(string $songNamesText): array
    {
        $names = [];

        foreach (preg_split('/\R/', $songNamesText) ?: [] as $line) {
            $name = trim($line);

            if ($name === '') {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param  array<int, int>  $instrumentPartIds
     * @return array<int, InstrumentPart>
     */
    private function resolveInstrumentParts(Band $band, array $instrumentPartIds): array
    {
        if ($instrumentPartIds === []) {
            return [];
        }

        $parts = InstrumentPart::query()
            ->where('band_id', $band->id)
            ->whereIn('id', $instrumentPartIds)
            ->get()
            ->keyBy('id');

        return collect($instrumentPartIds)
            ->unique()
            ->map(fn (int $id) => $parts->get($id))
            ->filter()
            ->values()
            ->all();
    }
}
