<?php

namespace App\Services;

use App\Models\Musician;
use App\Models\User;

class StudioMusicianResolverService
{
    public function musicianForUser(User $user, ?int $bandId = null): ?Musician
    {
        $bandId ??= (int) config('portal.band_id', 1);

        $byUserId = Musician::query()
            ->where('band_id', $bandId)
            ->where('user_id', $user->id)
            ->first();

        if ($byUserId !== null) {
            return $byUserId;
        }

        $user->loadMissing('person');

        if ($user->person === null) {
            return null;
        }

        if (filled($user->person->email)) {
            $byEmail = Musician::query()
                ->where('band_id', $bandId)
                ->where('email', $user->person->email)
                ->first();

            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        $legalName = trim($user->person->legalName());
        if ($legalName === '') {
            return null;
        }

        return Musician::query()
            ->where('band_id', $bandId)
            ->where(function ($query) use ($legalName, $user): void {
                $query->where('display_name', $legalName)
                    ->orWhereRaw("trim(concat(first_name, ' ', last_name)) = ?", [$legalName]);
            })
            ->first();
    }
}
