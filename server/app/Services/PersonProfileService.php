<?php

namespace App\Services;

use App\Models\InstrumentReference;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class PersonProfileService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Person $person, array $payload): Person
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

        return DB::transaction(function () use ($person, $payload, $primarySlug, $additionalSlugs, $instrumentMap): Person {
            $person->update([
                'artistic_name' => trim((string) $payload['stage_name']),
                'email' => trim((string) $payload['email']),
                'phone' => trim((string) $payload['telephone']),
                'city' => trim((string) $payload['city']),
                'country' => trim((string) $payload['country']),
            ]);

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
}
