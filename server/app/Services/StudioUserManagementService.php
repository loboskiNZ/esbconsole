<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudioUserManagementService
{
    /**
     * @var list<string>
     */
    public const MANAGEABLE_ROLE_KEYS = [
        Role::KEY_DIRECTOR,
        Role::KEY_MUSICIAN,
        Role::KEY_SOUND_TECH,
        Role::KEY_ASSISTANT,
    ];

    public function __construct(
        private readonly StudioRoleProvisioner $roleProvisioner,
    ) {}

    /**
     * @return Collection<int, array{
     *     user: User,
     *     person_name: string|null,
     *     role_keys: list<string>,
     *     role_labels: list<string>,
     * }>
     */
    public function usersForManagement(): Collection
    {
        $rolesByKey = $this->roleProvisioner->systemRoles()->keyBy('role_key');

        return User::query()
            ->with(['person', 'roles'])
            ->orderBy('username')
            ->get()
            ->map(function (User $user) use ($rolesByKey): array {
                $roleKeys = $user->roles
                    ->pluck('role_key')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $roleLabels = collect($roleKeys)
                    ->map(fn (string $roleKey): string => (string) ($rolesByKey[$roleKey]->name ?? $roleKey))
                    ->values()
                    ->all();

                return [
                    'user' => $user,
                    'person_name' => $this->personDisplayName($user),
                    'role_keys' => $roleKeys,
                    'role_labels' => $roleLabels,
                ];
            });
    }

    public function activate(User $target, User $actor): void
    {
        if ($target->is_active) {
            return;
        }

        $target->forceFill(['is_active' => true])->save();
    }

    public function deactivate(User $target, User $actor): void
    {
        if (! $target->is_active) {
            return;
        }

        if ($actor->id === $target->id && $this->activeDirectorCount(excludingUserId: $actor->id) === 0) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate your account while you are the only active director.'],
            ]);
        }

        $target->forceFill(['is_active' => false])->save();
    }

    /**
     * @param  list<string>  $roleKeys
     */
    public function syncRoles(User $target, array $roleKeys, User $actor): void
    {
        $roleKeys = $this->normalizeRoleKeys($roleKeys);

        if ($roleKeys === []) {
            throw ValidationException::withMessages([
                'roles' => ['Every user must keep at least one role.'],
            ]);
        }

        $currentRoleKeys = $target->roles->pluck('role_key')->filter()->unique()->values()->all();
        $removingDirector = in_array(Role::KEY_DIRECTOR, $currentRoleKeys, true)
            && ! in_array(Role::KEY_DIRECTOR, $roleKeys, true);

        if ($removingDirector) {
            if ($this->directorAssignmentCount(excludingUserId: $target->id) === 0) {
                throw ValidationException::withMessages([
                    'roles' => ['The system must keep at least one director assignment.'],
                ]);
            }

            if ($actor->id === $target->id && $this->activeDirectorCount(excludingUserId: $actor->id) === 0) {
                throw ValidationException::withMessages([
                    'roles' => ['You cannot remove your director role while you are the only active director.'],
                ]);
            }
        }

        $bandId = (int) config('portal.band_id', 1);
        $roles = Role::query()
            ->whereIn('role_key', $roleKeys)
            ->get()
            ->keyBy('role_key');

        $syncPayload = [];

        foreach ($roleKeys as $roleKey) {
            $role = $roles->get($roleKey);

            if ($role === null) {
                throw ValidationException::withMessages([
                    'roles' => ["The role \"{$roleKey}\" is not available."],
                ]);
            }

            $syncPayload[$role->id] = [
                'band_id' => $bandId,
                'assigned_at' => now(),
                'assigned_by' => $actor->id,
            ];
        }

        DB::transaction(function () use ($target, $syncPayload, $bandId): void {
            $existingRoleIds = $target->roles()
                ->wherePivot('band_id', $bandId)
                ->pluck('roles.id')
                ->all();

            $newRoleIds = array_map('intval', array_keys($syncPayload));

            foreach ($existingRoleIds as $roleId) {
                if (! in_array((int) $roleId, $newRoleIds, true)) {
                    $target->roles()->wherePivot('band_id', $bandId)->detach($roleId);
                }
            }

            $target->roles()->syncWithoutDetaching($syncPayload);
            $target->unsetRelation('roles');
        });
    }

    public function assignDefaultMusicianRole(User $user, ?int $assignedBy = null): bool
    {
        $this->roleProvisioner->provisionSystemRoles();

        return $this->roleProvisioner->assignMusicianToUser(
            userId: (int) $user->id,
            bandId: (int) config('portal.band_id', 1),
            assignedBy: $assignedBy,
        );
    }

    public function activeDirectorCount(?int $excludingUserId = null): int
    {
        return $this->usersWithDirectorRoleQuery($excludingUserId)
            ->where('is_active', true)
            ->count();
    }

    public function directorAssignmentCount(?int $excludingUserId = null): int
    {
        return $this->usersWithDirectorRoleQuery($excludingUserId)->count();
    }

    private function usersWithDirectorRoleQuery(?int $excludingUserId = null)
    {
        $bandId = (int) config('portal.band_id', 1);

        return User::query()
            ->when($excludingUserId !== null, fn ($query) => $query->where('users.id', '!=', $excludingUserId))
            ->whereHas('roles', function ($query) use ($bandId): void {
                $query->where('roles.role_key', Role::KEY_DIRECTOR)
                    ->where(function ($query) use ($bandId): void {
                        $query->whereNull('user_roles.band_id')
                            ->orWhere('user_roles.band_id', $bandId);
                    });
            });
    }

    /**
     * @param  list<string>  $roleKeys
     * @return list<string>
     */
    private function normalizeRoleKeys(array $roleKeys): array
    {
        $normalized = [];

        foreach ($roleKeys as $roleKey) {
            if (! is_string($roleKey)) {
                continue;
            }

            $roleKey = trim($roleKey);

            if ($roleKey === '' || ! in_array($roleKey, self::MANAGEABLE_ROLE_KEYS, true)) {
                continue;
            }

            $normalized[$roleKey] = $roleKey;
        }

        return array_values($normalized);
    }

    private function personDisplayName(User $user): ?string
    {
        $person = $user->person;

        if ($person === null) {
            return null;
        }

        if (filled($person->artistic_name)) {
            return $person->artistic_name;
        }

        $legalName = trim($person->legalName());

        return $legalName !== '' ? $legalName : null;
    }
}
