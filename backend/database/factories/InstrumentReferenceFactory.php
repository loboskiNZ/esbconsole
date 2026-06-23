<?php

namespace Database\Factories;

use App\Models\InstrumentReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentReference>
 */
class InstrumentReferenceFactory extends Factory
{
    protected $model = InstrumentReference::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Electric Guitar', 'Acoustic Guitar', 'Drums', 'Bass', 'Keys', 'Lead Vocal']),
            'family' => fake()->optional()->randomElement(['strings', 'percussion', 'vocals', 'keys']),
            'is_active' => true,
        ];
    }
}
