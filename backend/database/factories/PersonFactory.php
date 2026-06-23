<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        $legalFirst = fake()->firstName();
        $legalLast = fake()->lastName();

        return [
            'band_id' => Band::factory(),
            'legal_first_name' => $legalFirst,
            'legal_middle_names' => fake()->optional()->firstName(),
            'legal_last_name' => $legalLast,
            'artistic_name' => fake()->optional()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'gender' => fake()->optional()->randomElement(['female', 'male', 'non-binary']),
            'pronouns' => fake()->optional()->randomElement(['she/her', 'he/him', 'they/them']),
            'city' => fake()->optional()->city(),
            'country' => fake()->optional()->country(),
            'dietary_requirements' => fake()->optional()->sentence(),
            'notes' => null,
        ];
    }
}
