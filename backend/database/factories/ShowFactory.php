<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Show>
 */
class ShowFactory extends Factory
{
    protected $model = Show::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'name' => 'Test Show '.fake()->unique()->word(),
            'lifecycle_state' => 'draft',
        ];
    }
}
