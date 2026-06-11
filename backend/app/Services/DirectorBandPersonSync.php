<?php

namespace App\Services;

use App\Enums\BandRole;
use App\Models\Band;
use App\Models\Musician;
use App\Models\User;

class DirectorBandPersonSync
{
    public function __construct(
        private readonly BandPersonRoleSync $bandPersonRoleSync,
    ) {}

    public function sync(User $user, ?Band $band = null): ?Musician
    {
        $band ??= Band::query()->orderBy('id')->first();

        if ($band === null) {
            return null;
        }

        $person = $this->findExistingPerson($band, $user);

        if ($person === null) {
            $person = Musician::create([
                'band_id' => $band->id,
                'user_id' => $user->id,
                'first_name' => $this->firstNameFromUser($user),
                'last_name' => $this->lastNameFromUser($user),
                'display_name' => $user->name,
                'email' => $user->email,
                'active' => true,
            ]);
        } else {
            $person->update([
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        $roles = $person->fresh(['bandRoles'])->bandRoleValues();

        if (! in_array(BandRole::Director->value, $roles, true)) {
            $roles[] = BandRole::Director->value;
        }

        $this->bandPersonRoleSync->sync($person, $roles);

        if ($band->primary_director_musician_id === null) {
            $band->update(['primary_director_musician_id' => $person->id]);
        }

        return $person->fresh(['bandRoles', 'user']);
    }

    private function findExistingPerson(Band $band, User $user): ?Musician
    {
        $byUser = Musician::query()->where('user_id', $user->id)->first();

        if ($byUser !== null) {
            return $byUser;
        }

        if ($user->email === null) {
            return null;
        }

        return Musician::query()
            ->where('band_id', $band->id)
            ->where('email', $user->email)
            ->first();
    }

    private function firstNameFromUser(User $user): string
    {
        $parts = preg_split('/\s+/', trim($user->name), 2);

        return $parts[0] !== '' ? $parts[0] : 'Director';
    }

    private function lastNameFromUser(User $user): string
    {
        $parts = preg_split('/\s+/', trim($user->name), 2);

        return $parts[1] ?? '';
    }
}
