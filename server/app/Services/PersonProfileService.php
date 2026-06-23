<?php

namespace App\Services;

use App\Models\InstrumentReference;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PersonProfileService
{
    public function __construct(
        private readonly PersonProfilePhotoService $photoService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Person $person, array $payload, ?UploadedFile $photo = null): Person
    {
        $primarySlug = (string) $payload['primary_instrument'];
        $additionalSlugs = array_values(array_unique(array_filter(
            is_array($payload['additional_instruments'] ?? null) ? $payload['additional_instruments'] : [],
            fn ($slug) => is_string($slug) && $slug !== '' && $slug !== $primarySlug,
        )));

        $instrumentMap = InstrumentReference::query()
            ->whereIn('slug', array_merge([$primarySlug], $additionalSlugs))
            ->get()
            ->keyBy('slug');

        return DB::transaction(function () use ($person, $payload, $photo, $primarySlug, $additionalSlugs, $instrumentMap): Person {
            $updates = [
                'artistic_name' => trim((string) $payload['stage_name']),
                'email' => trim((string) $payload['email']),
                'phone' => trim((string) $payload['telephone']),
                'city' => trim((string) $payload['city']),
                'country' => trim((string) $payload['country']),
                'bio' => $this->normalizeBio($payload['bio'] ?? null),
            ];

            if ($photo !== null) {
                $updates['profile_photo_path'] = $this->photoService->store($person, $photo);
            }

            $person->update($updates);

            $person->instruments()->detach();

            $person->instruments()->attach($instrumentMap[$primarySlug]->id, [
                'is_primary' => true,
            ]);

            foreach ($additionalSlugs as $slug) {
                $person->instruments()->attach($instrumentMap[$slug]->id, [
                    'is_primary' => false,
                ]);
            }

            return $person->fresh(['instruments']);
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
