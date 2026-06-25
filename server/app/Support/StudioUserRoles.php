<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

class StudioUserRoles
{
  public static function hasRole(User $user, string $code, ?int $bandId = null): bool
  {
    if (! $user->relationLoaded('roles')) {
      $user->load('roles');
    }

    $bandId ??= (int) config('portal.band_id', 1);

    return $user->roles
      ->contains(function (Role $role) use ($code, $bandId): bool {
        if ($role->code !== $code) {
          return false;
        }

        $assignedBandId = $role->pivot?->band_id;

        return $assignedBandId === null || (int) $assignedBandId === $bandId;
      });
  }

  public static function isDirector(User $user, ?int $bandId = null): bool
  {
    return self::hasRole($user, Role::CODE_DIRECTOR, $bandId);
  }
}
