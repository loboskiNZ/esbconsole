<?php

namespace App\Services;

use App\Models\AbletonShowFile;
use App\Models\Show;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudioShowService
{
    /**
     * @return Collection<int, Show>
     */
    public function showsForPortal(?int $bandId = null, ?int $limit = null): Collection
    {
        $bandId ??= (int) config('portal.band_id', 1);

        $query = Show::query()
            ->where('band_id', $bandId)
            ->orderByDesc('scheduled_at')
            ->orderBy('name');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     scheduled_at?: string|null,
     *     venue_location?: string|null,
     *     notes?: string|null,
     *     lifecycle_state?: string|null,
     * }  $payload
     */
    public function createShow(array $payload, ?int $bandId = null): Show
    {
        $bandId ??= (int) config('portal.band_id', 1);
        $name = trim($payload['name']);
        $publicId = (string) Str::uuid();
        $placeholderReference = 'pending/ableton/'.Str::slug($name).'-'.Str::lower(Str::substr($publicId, 0, 8)).'.als';

        return DB::transaction(function () use ($payload, $bandId, $name, $publicId, $placeholderReference): Show {
            $abletonShowFile = AbletonShowFile::query()->create([
                'public_id' => (string) Str::uuid(),
                'band_id' => $bandId,
                'name' => $name.' — Ableton Show File',
                'storage_reference' => $placeholderReference,
                'checksum' => hash('sha256', 'studio-placeholder:'.$publicId),
                'notes' => 'Placeholder pending Ableton show file attachment.',
            ]);

            return Show::query()->create([
                'public_id' => $publicId,
                'band_id' => $bandId,
                'ableton_show_file_id' => $abletonShowFile->id,
                'name' => $name,
                'description' => $this->normalizeNullableString($payload['notes'] ?? null),
                'lifecycle_state' => $payload['lifecycle_state'] ?? Show::STATE_DRAFT,
                'scheduled_at' => $payload['scheduled_at'] ?? null,
                'venue_location' => $this->normalizeNullableString($payload['venue_location'] ?? null),
            ]);
        });
    }

    public function showForPortal(int $showId, ?int $bandId = null): Show
    {
        $bandId ??= (int) config('portal.band_id', 1);

        return Show::query()
            ->where('band_id', $bandId)
            ->findOrFail($showId);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
