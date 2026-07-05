<?php

namespace App\Services;

use App\Models\InviteLinkAcceptance;
use App\Models\Musician;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudioMusicianResolverService
{
    public function musicianForUser(User $user, ?int $bandId = null): ?Musician
    {
        $portalBandId = $bandId ?? (int) config('portal.band_id', 1);

        $byUserId = Musician::query()
            ->where('band_id', $portalBandId)
            ->where('user_id', $user->id)
            ->first();

        if ($byUserId !== null) {
            return $byUserId;
        }

        $user->loadMissing('person');

        foreach ($this->candidateEmails($user) as $email) {
            $byEmail = $this->bandMusiciansQuery($portalBandId)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->orderByDesc('user_id')
                ->first();

            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        foreach ($this->candidateNames($user) as $name) {
            $normalizedName = strtolower($name);

            $byName = $this->bandMusiciansQuery($portalBandId)
                ->where(function (Builder $query) use ($normalizedName): void {
                    $query->whereRaw('LOWER(TRIM(display_name)) = ?', [$normalizedName])
                        ->orWhereRaw("LOWER(TRIM(CONCAT(first_name, ' ', last_name))) = ?", [$normalizedName]);
                })
                ->orderByDesc('user_id')
                ->first();

            if ($byName !== null) {
                return $byName;
            }
        }

        return $this->materializePortalMusician($user, $portalBandId);
    }

    private function materializePortalMusician(User $user, int $portalBandId): ?Musician
    {
        if ($user->person_id === null || ! $user->hasRole(Role::KEY_MUSICIAN, $portalBandId)) {
            return null;
        }

        $person = $user->person;

        if ($person === null) {
            return null;
        }

        foreach ($this->candidateEmails($user) as $email) {
            $existing = Musician::query()
                ->where('band_id', $portalBandId)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->orderByDesc('active')
                ->orderByDesc('user_id')
                ->first();

            if ($existing !== null) {
                return $this->linkMusicianToUser($existing, $user);
            }
        }

        foreach ($this->candidateNames($user) as $name) {
            $normalizedName = strtolower($name);

            $existing = Musician::query()
                ->where('band_id', $portalBandId)
                ->where(function (Builder $query) use ($normalizedName): void {
                    $query->whereRaw('LOWER(TRIM(display_name)) = ?', [$normalizedName])
                        ->orWhereRaw("LOWER(TRIM(CONCAT(first_name, ' ', last_name))) = ?", [$normalizedName]);
                })
                ->orderByDesc('active')
                ->orderByDesc('user_id')
                ->first();

            if ($existing !== null) {
                return $this->linkMusicianToUser($existing, $user);
            }
        }

        if (! Schema::hasTable('invite_link_acceptances')
            || ! InviteLinkAcceptance::query()->where('user_id', $user->id)->exists()) {
            return null;
        }

        return $this->createMusicianFromPerson($user, $person, $portalBandId);
    }

    private function linkMusicianToUser(Musician $musician, User $user): ?Musician
    {
        if ($musician->user_id !== null && (int) $musician->user_id !== (int) $user->id) {
            return null;
        }

        if ($musician->user_id === null) {
            $musician->forceFill(['user_id' => $user->id])->save();
        }

        return $musician->fresh();
    }

    private function createMusicianFromPerson(User $user, Person $person, int $portalBandId): Musician
    {
        $firstName = trim((string) $person->legal_first_name);
        $lastName = trim((string) $person->legal_last_name);

        if ($firstName === '') {
            $firstName = trim((string) ($user->name ?: $user->username ?: 'Musician'));
        }

        if ($lastName === '') {
            $lastName = 'Musician';
        }

        $displayName = trim((string) ($person->artistic_name ?: $user->name ?: $user->username ?: $person->legalName()));

        if ($displayName === '') {
            $displayName = $firstName;
        }

        return Musician::create([
            'public_id' => (string) Str::uuid(),
            'band_id' => $portalBandId,
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName,
            'email' => $person->email ?? $user->email,
            'active' => true,
        ]);
    }

    private function bandMusiciansQuery(int $bandId): Builder
    {
        return Musician::query()
            ->where('band_id', $bandId)
            ->where('active', true);
    }

    /**
     * @return list<string>
     */
    private function candidateEmails(User $user): array
    {
        return collect([
            $user->email,
            $user->person?->email,
        ])
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function candidateNames(User $user): array
    {
        $names = collect([
            $user->username,
            $user->name,
            $user->person?->artistic_name,
        ]);

        if ($user->person !== null) {
            $legalName = trim($user->person->legalName());

            if ($legalName !== '') {
                $names->push($legalName);
            }
        }

        return $names
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values()
            ->all();
    }
}
