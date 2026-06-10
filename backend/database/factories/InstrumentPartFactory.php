<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\InstrumentPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentPart>
 */
class InstrumentPartFactory extends Factory
{
    protected $model = InstrumentPart::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'name' => fake()->randomElement(['Lead Vocal', 'Guitar', 'Bass', 'Drums', 'Trumpet']),
            'description' => null,
            'active' => true,
        ];
    }
}
