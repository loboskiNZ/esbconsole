<?php

namespace App\Services;

use App\Models\Musician;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StudioMusicianResolverService
{
    public function musicianForUser(User $user, ?int $bandId = null): ?Musician
    {
        $bandId ??= (int) ($user->band_id ?? config('portal.band_id', 1));

        $byUserId = $this->bandMusiciansQuery($bandId)
            ->where('user_id', $user->id)
            ->first();

        if ($byUserId !== null) {
            return $byUserId;
        }

        $user->loadMissing('person');

        foreach ($this->candidateEmails($user) as $email) {
            $byEmail = $this->bandMusiciansQuery($bandId)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->orderByDesc('user_id')
                ->first();

            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        foreach ($this->candidateNames($user) as $name) {
            $byName = $this->bandMusiciansQuery($bandId)
                ->where(function (Builder $query) use ($name): void {
                    $query->where('display_name', $name)
                        ->orWhereRaw("TRIM(CONCAT(first_name, ' ', last_name)) = ?", [$name]);
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
