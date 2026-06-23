<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = strtolower(fake()->unique()->bothify('user####'));

        return [
            'username' => $username,
            'password' => static::$password ??= Hash::make('Password1!'),
            'person_id' => Person::factory(),
            'band_id' => 1,
            'is_active' => true,
        ];
    }
}
