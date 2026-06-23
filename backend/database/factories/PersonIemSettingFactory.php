<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonIemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonIemSetting>
 */
class PersonIemSettingFactory extends Factory
{
    protected $model = PersonIemSetting::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'name' => fake()->randomElement(['Festival', 'Club', 'Rehearsal', 'Default']),
            'vocal_level' => fake()->optional()->randomFloat(2, 0, 1),
            'own_instrument_level' => fake()->optional()->randomFloat(2, 0, 1),
            'band_level' => fake()->optional()->randomFloat(2, 0, 1),
            'click_level' => fake()->optional()->randomFloat(2, 0, 1),
            'tracks_level' => fake()->optional()->randomFloat(2, 0, 1),
            'reverb_level' => fake()->optional()->randomFloat(2, 0, 1),
            'ambient_level' => fake()->optional()->randomFloat(2, 0, 1),
            'notes' => null,
        ];
    }
}
