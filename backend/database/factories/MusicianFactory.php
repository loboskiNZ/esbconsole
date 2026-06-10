<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\Musician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Musician>
 */
class MusicianFactory extends Factory
{
    protected $model = Musician::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'band_id' => Band::factory(),
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => "{$first} {$last}",
            'email' => fake()->unique()->safeEmail(),
            'notes' => null,
            'active' => true,
        ];
    }
}
