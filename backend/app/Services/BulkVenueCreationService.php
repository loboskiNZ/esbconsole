<?php

namespace App\Services;

use App\DataTransferObjects\BulkVenueCreationResult;
use App\Models\Band;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

class BulkVenueCreationService
{
    public function create(Band $band, string $venuesText): BulkVenueCreationResult
    {
        $rows = $this->parseLines($venuesText);

        $existingNames = $band->venues()
            ->pluck('name')
            ->map(fn (string $name) => Venue::normalizeName($name))
            ->flip()
            ->all();

        $seenInSubmission = [];
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($band, $rows, &$existingNames, &$seenInSubmission, &$created, &$skipped) {
            foreach ($rows as $row) {
                $name = $row['name'];
                $normalized = Venue::normalizeName($name);

                if (isset($seenInSubmission[$normalized])) {
                    $skipped[] = [
                        'name' => $name,
                        'reason' => 'Duplicate in submitted list.',
                    ];

                    continue;
                }

                $seenInSubmission[$normalized] = true;

                if (isset($existingNames[$normalized])) {
                    $skipped[] = [
                        'name' => $name,
                        'reason' => 'Venue already exists for this band.',
                    ];

                    continue;
                }

                $venue = Venue::create([
                    'band_id' => $band->id,
                    ...$row,
                    'active' => true,
                ]);

                $existingNames[$normalized] = true;

                $created[] = [
                    'name' => $venue->name,
                    'venue_id' => $venue->id,
                ];
            }
        });

        return new BulkVenueCreationResult($created, $skipped);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function parseLines(string $venuesText): array
    {
        $rows = [];

        foreach (preg_split('/\R/', $venuesText) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parsed = $this->parseLine($line);

            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function parseLine(string $line): ?array
    {
        $parts = array_map('trim', explode('|', $line));
        $name = $parts[0] ?? '';

        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'country' => $this->nullablePart($parts[1] ?? null),
            'city' => $this->nullablePart($parts[2] ?? null),
            'address' => $this->nullablePart($parts[3] ?? null),
            'contact_name' => $this->nullablePart($parts[4] ?? null),
            'contact_phone' => $this->nullablePart($parts[5] ?? null),
            'contact_email' => $this->nullablePart($parts[6] ?? null),
            'facebook_tag' => $this->nullablePart($parts[7] ?? null),
            'instagram_tag' => $this->nullablePart($parts[8] ?? null),
            'tiktok_tag' => $this->nullablePart($parts[9] ?? null),
        ];
    }

    private function nullablePart(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
