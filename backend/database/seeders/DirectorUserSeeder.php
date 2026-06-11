<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Local/dev Director login for show-builder access (PH032).
 * Not for production — runs only in local and testing environments.
 */
class DirectorUserSeeder extends Seeder
{
    public const DIRECTOR_EMAIL = 'ed@loboski.nz';

    public const DIRECTOR_PASSWORD = 'letmein';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        Role::findOrCreate('director');

        $user = User::query()->updateOrCreate(
            ['email' => self::DIRECTOR_EMAIL],
            [
                'name' => 'Ed',
                'password' => self::DIRECTOR_PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['director']);
    }
}
