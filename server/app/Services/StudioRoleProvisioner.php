<?php

namespace App\Services;

use App\Models\Role;
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
    Role::KEY_DIRECTOR => [
      'public_id' => '10000000-0000-4000-8000-000000000001',
      'name' => 'Director / Superuser',
      'description' => 'Full Cloud Studio administration and director surfaces.',
    ],
    Role::KEY_MUSICIAN => [
      'public_id' => '10000000-0000-4000-8000-000000000002',
      'name' => 'Musician',
      'description' => 'Musician-facing Studio surfaces.',
    ],
    Role::KEY_SOUND_TECH => [
      'public_id' => '10000000-0000-4000-8000-000000000003',
      'name' => 'Sound Tech',
      'description' => 'Sound and technical operations surfaces.',
    ],
    Role::KEY_ASSISTANT => [
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

    foreach (self::SYSTEM_ROLES as $roleKey => $definition) {
      if ($this->findExistingRoleByKey($roleKey) !== null) {
        continue;
      }

      $payload = [
        'public_id' => $definition['public_id'],
        'name' => $definition['name'],
        'description' => $definition['description'],
        'is_system' => true,
        'created_at' => $now,
        'updated_at' => $now,
      ];

      if (Schema::hasColumn('roles', 'role_key')) {
        $payload['role_key'] = $roleKey;
      } elseif (Schema::hasColumn('roles', 'code')) {
        $payload['code'] = $roleKey;
      }

      DB::table('roles')->insert($payload);

      $created++;
    }

    return $created;
  }

  public function assignDirectorToUser(int $userId, ?int $bandId = null, ?int $assignedBy = null): bool
  {
    $role = $this->findExistingRoleByKey(Role::KEY_DIRECTOR);

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
    $query = Role::query()->orderBy('id');

    if (Schema::hasColumn('roles', 'role_key')) {
      return $query
        ->whereIn('role_key', array_keys(self::SYSTEM_ROLES))
        ->get();
    }

    return $query
      ->whereIn('code', array_keys(self::SYSTEM_ROLES))
      ->get();
  }

  private function findExistingRoleByKey(string $roleKey): ?object
  {
    if (Schema::hasColumn('roles', 'role_key')) {
      $existing = DB::table('roles')->where('role_key', $roleKey)->first();

      if ($existing !== null) {
        return $existing;
      }
    }

    if (Schema::hasColumn('roles', 'code')) {
      return DB::table('roles')->where('code', $roleKey)->first();
    }

    return null;
  }

  private function usersCount(): int
  {
    if (! Schema::hasTable('users')) {
      return 0;
    }

    return (int) DB::table('users')->count();
  }
}
