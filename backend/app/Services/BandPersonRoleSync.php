<?php

namespace App\Services;

use App\Enums\BandRole;
use App\Models\Musician;
use App\Models\MusicianBandRole;

class BandPersonRoleSync
{
    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public function normalizeRoles(array $roles): array
    {
        $normalized = collect($roles)
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return [BandRole::Musician->value];
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function sync(Musician $musician, array $roles): void
    {
        $roles = $this->normalizeRoles($roles);

        MusicianBandRole::query()->where('musician_id', $musician->id)->delete();

        foreach ($roles as $role) {
            MusicianBandRole::create([
                'musician_id' => $musician->id,
                'role' => $role,
            ]);
        }
    }
}
