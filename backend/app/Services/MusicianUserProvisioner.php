<?php

namespace App\Services;

use App\Models\Musician;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class MusicianUserProvisioner
{
    public function __construct(
        private readonly MusicianLoginPasswordGenerator $passwordGenerator,
    ) {}

    /**
     * @return array{user: User, plain_password: string}
     */
    public function provision(Musician $musician, string $email): array
    {
        if ($musician->user_id !== null) {
            throw ValidationException::withMessages([
                'create_login_account' => 'This musician already has a login account.',
            ]);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A user account with this email already exists.',
            ]);
        }

        Role::findOrCreate('musician');

        $plainPassword = $this->passwordGenerator->generate();

        return DB::transaction(function () use ($musician, $email, $plainPassword) {
            $user = User::create([
                'name' => $musician->display_name,
                'email' => $email,
                'password' => $plainPassword,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('musician');

            $musician->update(['user_id' => $user->id]);

            return [
                'user' => $user,
                'plain_password' => $plainPassword,
            ];
        });
    }
}
