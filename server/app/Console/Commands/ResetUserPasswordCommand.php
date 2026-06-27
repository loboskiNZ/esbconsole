<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetUserPasswordCommand extends Command
{
    protected $signature = 'esb:reset-user-password
                            {username : Portal username to reset}
                            {--password= : New password (must meet onboarding policy)}
                            {--generate : Generate a random compliant password}';

    protected $description = 'Reset a Studio portal user password (operator use on Forge/SSH)';

    public function handle(): int
    {
        $username = Str::lower(trim($this->argument('username')));
        $user = User::query()->where('username', $username)->first();

        if ($user === null) {
            $this->error("No user found with username [{$username}].");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: '');

        if ($password === '' && $this->option('generate')) {
            $password = $this->generatePassword();
        }

        if ($password === '') {
            $this->error('Provide --password=... or --generate.');

            return self::FAILURE;
        }

        try {
            $this->validatePassword($password);
        } catch (ValidationException $exception) {
            foreach ($exception->errors()['password'] ?? [] as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->line('Password reset for user ['.$user->username.'].');
        $this->line('');
        $this->line('Username: '.$user->username);
        $this->line('Password: '.$password);
        $this->line('');
        $this->warn('Share this password securely. Users can also reset via Forgot password on the login page.');

        return self::SUCCESS;
    }

    private function generatePassword(): string
    {
        $core = Str::upper(Str::random(4)).Str::lower(Str::random(4)).random_int(10, 99);

        return 'Esb'.$core.'!';
    }

    /**
     * @throws ValidationException
     */
    private function validatePassword(string $password): void
    {
        validator(
            ['password' => $password],
            [
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:50',
                    'regex:/[A-Z]/',
                    'regex:/[a-z]/',
                    'regex:/[0-9]/',
                    'regex:/[^A-Za-z0-9]/',
                ],
            ],
        )->validate();
    }
}
