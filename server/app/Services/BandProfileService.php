<?php

namespace App\Services;

use App\Models\Band;
use App\Support\BandStyles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BandProfileService
{
    /**
     * @var list<string>
     */
    private const TEXT_FIELDS = [
        'name',
        'short_name',
        'tagline',
        'hometown',
        'bio',
        'short_bio',
        'full_bio',
        'booking_email',
        'booking_phone',
        'website_url',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'youtube_url',
        'spotify_url',
        'apple_music_url',
        'bandcamp_url',
    ];

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
        ?UploadedFile $heroPhoto = null,
        ?UploadedFile $pressPhoto = null,
    ): Band {
        return DB::transaction(function () use ($band, $payload, $logo, $photo, $heroPhoto, $pressPhoto): Band {
            $updates = [];

            foreach (self::TEXT_FIELDS as $field) {
                if (! array_key_exists($field, $payload)) {
                    continue;
                }

                $updates[$field] = $this->normalizeNullableString($payload[$field]);
            }

            if (array_key_exists('formation_year', $payload)) {
                $updates['formation_year'] = $this->normalizeFormationYear($payload['formation_year']);
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

            if ($heroPhoto !== null) {
                $updates['hero_photo_path'] = $this->assetStorage->storeHeroPhoto($band, $heroPhoto);
            }

            if ($pressPhoto !== null) {
                $updates['press_photo_path'] = $this->assetStorage->storePressPhoto($band, $pressPhoto);
            }

            if ($updates !== []) {
                $band->update($updates);
            }

            return $band->fresh();
        });
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeFormationYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
