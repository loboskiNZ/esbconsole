<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudioRoleProvisioner
{
  public const DIRECTOR_USERNAME = 'loboski';

  public const DIRECTOR_EMAIL = 'ed@loboski.nz';

  /**
   * @var array<string, array{public_id: string, name: string, description: string}>
   */
  private const SYSTEM_ROLES = [
    Role::CODE_DIRECTOR => [
      'public_id' => '10000000-0000-4000-8000-000000000001',
      'name' => 'Director / Superuser',
      'description' => 'Full Cloud Studio administration and director surfaces.',
    ],
    Role::CODE_MUSICIAN => [
      'public_id' => '10000000-0000-4000-8000-000000000002',
      'name' => 'Musician',
      'description' => 'Musician-facing Studio surfaces.',
    ],
    Role::CODE_SOUND_TECH => [
      'public_id' => '10000000-0000-4000-8000-000000000003',
      'name' => 'Sound Tech',
      'description' => 'Sound and technical operations surfaces.',
    ],
    Role::CODE_ASSISTANT => [
      'public_id' => '10000000-0000-4000-8000-000000000004',
      'name' => 'Assistant',
      'description' => 'Assistant and support surfaces.',
    ],
  ];

  /**
   * @return array{
   *     users_count_before: int,
   *     users_count_after: int,
   *     roles_created: int,
   *     director_assigned: bool,
   *     director_user_id: int|null,
   * }
   */
  public function provision(?int $bandId = null): array
  {
    if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
      return [
        'users_count_before' => 0,
        'users_count_after' => 0,
        'roles_created' => 0,
        'director_assigned' => false,
        'director_user_id' => null,
      ];
    }

    $usersCountBefore = $this->usersCount();
    $rolesCreated = $this->provisionSystemRoles();
    $directorUser = $this->findDirectorUser();
    $directorAssigned = false;

    if ($directorUser !== null) {
      $directorAssigned = $this->assignDirectorToUser(
        userId: (int) $directorUser->id,
        bandId: $bandId ?? (int) config('portal.band_id', 1),
      );
    }

    return [
      'users_count_before' => $usersCountBefore,
      'users_count_after' => $this->usersCount(),
      'roles_created' => $rolesCreated,
      'director_assigned' => $directorAssigned,
      'director_user_id' => $directorUser?->id !== null ? (int) $directorUser->id : null,
    ];
  }

  public function provisionSystemRoles(): int
  {
    $created = 0;
    $now = now();

    foreach (self::SYSTEM_ROLES as $code => $definition) {
      $existing = DB::table('roles')->where('code', $code)->first();

      if ($existing !== null) {
        continue;
      }

      DB::table('roles')->insert([
        'public_id' => $definition['public_id'],
        'code' => $code,
        'name' => $definition['name'],
        'description' => $definition['description'],
        'is_system' => true,
        'created_at' => $now,
        'updated_at' => $now,
      ]);

      $created++;
    }

    return $created;
  }

  public function assignDirectorToUser(int $userId, ?int $bandId = null, ?int $assignedBy = null): bool
  {
    $role = Role::query()->where('code', Role::CODE_DIRECTOR)->first();

    if ($role === null) {
      return false;
    }

    $bandId ??= (int) config('portal.band_id', 1);

    UserRole::query()->firstOrCreate(
      [
        'user_id' => $userId,
        'role_id' => $role->id,
        'band_id' => $bandId,
      ],
      [
        'assigned_at' => now(),
        'assigned_by' => $assignedBy,
      ],
    );

    return true;
  }

  public function findDirectorUser(): ?object
  {
    if (! Schema::hasTable('users')) {
      return null;
    }

    $user = DB::table('users')
      ->whereRaw('LOWER(username) = ?', [strtolower(self::DIRECTOR_USERNAME)])
      ->first();

    if ($user !== null) {
      return $user;
    }

    $user = DB::table('users')
      ->whereRaw('LOWER(email) = ?', [strtolower(self::DIRECTOR_EMAIL)])
      ->first();

    if ($user !== null) {
      return $user;
    }

    if (! Schema::hasTable('people')) {
      return null;
    }

    return DB::table('users')
      ->join('people', 'users.person_id', '=', 'people.id')
      ->whereRaw('LOWER(people.email) = ?', [strtolower(self::DIRECTOR_EMAIL)])
      ->select('users.*')
      ->first();
  }

  /**
   * @return Collection<int, Role>
   */
  public function systemRoles(): Collection
  {
    return Role::query()
      ->whereIn('code', array_keys(self::SYSTEM_ROLES))
      ->orderBy('id')
      ->get();
  }

  private function usersCount(): int
  {
    if (! Schema::hasTable('users')) {
      return 0;
    }

    return (int) DB::table('users')->count();
  }
}
