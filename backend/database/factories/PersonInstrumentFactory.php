<?php

namespace Database\Factories;

use App\Models\InstrumentReference;
use App\Models\Person;
use App\Models\PersonInstrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonInstrument>
 */
class PersonInstrumentFactory extends Factory
{
    protected $model = PersonInstrument::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'instrument_id' => InstrumentReference::factory(),
            'role_label' => fake()->optional()->randomElement(['Lead', 'Rhythm', 'Harmony']),
            'is_primary' => false,
            'notes' => null,
        ];
    }
}
