<?php

namespace App\Services;

use App\DataTransferObjects\BulkFestivalCreationResult;
use App\Enums\FestivalApplicationStatus;
use App\Models\Band;
use App\Models\Festival;
use Illuminate\Support\Facades\DB;

class BulkFestivalCreationService
{
    public function create(Band $band, string $festivalsText): BulkFestivalCreationResult
    {
        $rows = $this->parseLines($festivalsText);

        $existingNames = $band->festivals()
            ->pluck('name')
            ->map(fn (string $name) => Festival::normalizeName($name))
            ->flip()
            ->all();

        $seenInSubmission = [];
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($band, $rows, &$existingNames, &$seenInSubmission, &$created, &$skipped) {
            foreach ($rows as $row) {
                $name = $row['name'];
                $normalized = Festival::normalizeName($name);

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
                        'reason' => 'Festival already exists for this band.',
                    ];

                    continue;
                }

                $festival = Festival::create([
                    'band_id' => $band->id,
                    ...$row,
                    'active' => true,
                ]);

                $existingNames[$normalized] = true;

                $created[] = [
                    'name' => $festival->name,
                    'festival_id' => $festival->id,
                ];
            }
        });

        return new BulkFestivalCreationResult($created, $skipped);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseLines(string $festivalsText): array
    {
        $rows = [];

        foreach (preg_split('/\R/', $festivalsText) ?: [] as $line) {
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
     * @return array<string, mixed>|null
     */
    private function parseLine(string $line): ?array
    {
        $parts = array_map('trim', explode('|', $line));
        $name = $parts[0] ?? '';

        if ($name === '') {
            return null;
        }

        $status = FestivalApplicationStatus::normalize($this->nullablePart($parts[10] ?? null));

        return [
            'name' => $name,
            'country' => $this->nullablePart($parts[1] ?? null),
            'city' => $this->nullablePart($parts[2] ?? null),
            'website' => $this->nullablePart($parts[3] ?? null),
            'contact_name' => $this->nullablePart($parts[4] ?? null),
            'contact_phone' => $this->nullablePart($parts[5] ?? null),
            'contact_email' => $this->nullablePart($parts[6] ?? null),
            'application_url' => $this->nullablePart($parts[7] ?? null),
            'application_deadline' => $this->parseDeadline($parts[8] ?? null),
            'festival_date_notes' => $this->nullablePart($parts[9] ?? null),
            'application_status' => $status->value,
            'facebook_tag' => $this->nullablePart($parts[11] ?? null),
            'instagram_tag' => $this->nullablePart($parts[12] ?? null),
            'tiktok_tag' => $this->nullablePart($parts[13] ?? null),
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

    private function parseDeadline(?string $value): ?string
    {
        $value = $this->nullablePart($value);

        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
