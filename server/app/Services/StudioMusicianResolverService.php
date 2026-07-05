<?php

namespace App\Services;

use App\Models\Musician;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

        return null;
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
