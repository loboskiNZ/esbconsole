<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use App\Services\StudioRoleProvisioner;

trait AssignsStudioRoles
{
    protected function provisionStudioRoles(): void
    {
        app(StudioRoleProvisioner::class)->provisionSystemRoles();
    }

    protected function assignDirectorRole(User $user, ?int $bandId = null): void
    {
        $this->provisionStudioRoles();

        app(StudioRoleProvisioner::class)->assignDirectorToUser(
            userId: $user->id,
            bandId: $bandId ?? (int) config('portal.band_id', 1),
        );

        $user->unsetRelation('roles');
    }

    protected function assignMusicianRole(User $user, ?int $bandId = null): void
    {
        $this->provisionStudioRoles();

        $role = Role::query()->where('code', Role::CODE_MUSICIAN)->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'band_id' => $bandId ?? (int) config('portal.band_id', 1),
                'assigned_at' => now(),
            ],
        ]);

        $user->unsetRelation('roles');
    }

    protected function createDirectorUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->assignDirectorRole($user);

        return $user;
    }
}
