<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

class StudioUserRoles
{
  public static function hasRole(User $user, string $roleKey, ?int $bandId = null): bool
  {
    if (! $user->relationLoaded('roles')) {
      $user->load('roles');
    }

    $bandId ??= (int) config('portal.band_id', 1);

    return $user->roles
      ->contains(function (Role $role) use ($roleKey, $bandId): bool {
        if ($role->role_key !== $roleKey) {
          return false;
        }

        $assignedBandId = $role->pivot?->band_id;

        return $assignedBandId === null || (int) $assignedBandId === $bandId;
      });
  }

  public static function isDirector(User $user, ?int $bandId = null): bool
  {
    return self::hasRole($user, Role::KEY_DIRECTOR, $bandId);
  }
}
