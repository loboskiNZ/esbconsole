<?php

namespace Database\Factories;

use App\Models\Band;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Band>
 */
class BandFactory extends Factory
{
    protected $model = Band::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' (Test Band)',
        ];
    }
}
