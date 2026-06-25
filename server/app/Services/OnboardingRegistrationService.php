<?php

namespace App\Services;

use App\Models\InstrumentReference;
use App\Models\InviteLink;
use App\Models\InviteLinkAcceptance;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardingRegistrationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function register(InviteLink $inviteLink, array $payload): User
    {
        if (! $inviteLink->canAcceptAnotherRegistration()) {
            throw ValidationException::withMessages([
                'invite' => ['This invitation is no longer valid.'],
            ]);
        }

        $username = (string) $payload['username'];
        $password = (string) $payload['password'];
        $primarySlug = (string) $payload['primary_instrument'];
        $additionalSlugs = array_values(array_unique(array_filter(
            is_array($payload['additional_instruments'] ?? null) ? $payload['additional_instruments'] : [],
            fn ($slug) => is_string($slug) && $slug !== '',
        )));

        $instrumentMap = InstrumentReference::query()
            ->whereIn('slug', array_merge([$primarySlug], $additionalSlugs))
            ->get()
            ->keyBy('slug');

        if (! $instrumentMap->has($primarySlug)) {
            throw ValidationException::withMessages([
                'primary_instrument' => ['That instrument is not available in the band catalog. Choose another weapon.'],
            ]);
        }

        foreach ($additionalSlugs as $slug) {
            if (! $instrumentMap->has($slug)) {
                throw ValidationException::withMessages([
                    'additional_instruments' => ['One of the additional instruments is not available. Review your weapon choices.'],
                ]);
            }
        }

        $bandId = (int) config('portal.band_id');

        return DB::transaction(function () use ($inviteLink, $payload, $username, $password, $primarySlug, $additionalSlugs, $instrumentMap, $bandId): User {
            $person = Person::create([
                'public_id' => (string) Str::uuid(),
                'band_id' => $bandId,
                'legal_first_name' => trim((string) $payload['first_name']),
                'legal_middle_names' => $this->nullableTrim($payload['middle_name'] ?? null),
                'legal_last_name' => trim((string) $payload['surname']),
                'artistic_name' => trim((string) $payload['stage_name']),
                'email' => trim((string) $payload['email']),
                'phone' => trim((string) $payload['telephone']),
                'city' => trim((string) $payload['city']),
                'country' => trim((string) $payload['country']),
            ]);

            $user = User::create([
                'public_id' => (string) Str::uuid(),
                'username' => $username,
                'password' => $password,
                'person_id' => $person->id,
                'band_id' => $bandId,
                'is_active' => true,
                'email' => trim((string) $payload['email']),
                'name' => trim((string) $payload['stage_name']),
            ]);

            $person->instruments()->attach($instrumentMap[$primarySlug]->id, [
                'is_primary' => true,
            ]);

            foreach ($additionalSlugs as $slug) {
                $person->instruments()->attach($instrumentMap[$slug]->id, [
                    'is_primary' => false,
                ]);
            }

            InviteLinkAcceptance::create([
                'invite_link_id' => $inviteLink->id,
                'person_id' => $person->id,
                'user_id' => $user->id,
                'accepted_at' => now(),
            ]);

            $inviteLink->increment('used_count');

            app(StudioUserManagementService::class)->assignDefaultMusicianRole($user);

            return $user;
        });
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
