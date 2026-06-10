<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

trait CreatesDirectorUser
{
    protected function createDirectorUser(array $attributes = []): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findByName('director'));

        return $user;
    }
}
