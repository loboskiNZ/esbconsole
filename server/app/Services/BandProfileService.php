<?php

namespace App\Services;

use App\Models\Band;
use App\Support\BandStyles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BandProfileService
{
    public function __construct(
        private readonly BandAssetStorageService $assetStorage,
    ) {}

    public function portalBand(): Band
    {
        return Band::query()->findOrFail((int) config('portal.band_id', 1));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(
        Band $band,
        array $payload,
        ?UploadedFile $logo = null,
        ?UploadedFile $photo = null,
    ): Band {
        return DB::transaction(function () use ($band, $payload, $logo, $photo): Band {
            $updates = [];

            if (array_key_exists('name', $payload)) {
                $updates['name'] = trim((string) $payload['name']);
            }

            if (array_key_exists('bio', $payload)) {
                $updates['bio'] = $this->normalizeBio($payload['bio'] ?? null);
            }

            if (array_key_exists('styles', $payload)) {
                $updates['styles'] = BandStyles::normalize($payload['styles']);
            }

            if ($logo !== null) {
                $updates['logo_path'] = $this->assetStorage->storeLogo($band, $logo);
            }

            if ($photo !== null) {
                $updates['photo_path'] = $this->assetStorage->storePhoto($band, $photo);
            }

            if ($updates !== []) {
                $band->update($updates);
            }

            return $band->fresh();
        });
    }

    private function normalizeBio(mixed $bio): ?string
    {
        if (! is_string($bio)) {
            return null;
        }

        $trimmed = trim($bio);

        return $trimmed === '' ? null : $trimmed;
    }
}
