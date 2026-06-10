<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateDirectorCommand extends Command
{
    protected $signature = 'esb:create-director
                            {--name= : Director display name}
                            {--email= : Login email}
                            {--password= : Login password}
                            {--role=director : Role to assign (director or administrator)}';

    protected $description = 'Create a local Director or Administrator user (not for production seeding)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $roleName = $this->option('role');
        if (! in_array($roleName, ['director', 'administrator'], true)) {
            $this->error('Role must be director or administrator.');

            return self::FAILURE;
        }

        Role::findOrCreate($roleName);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($roleName);

        $this->info("Created {$roleName} user: {$user->email}");

        return self::SUCCESS;
    }
}
