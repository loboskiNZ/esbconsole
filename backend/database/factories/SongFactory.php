<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Song>
 */
class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'name' => 'Test Song '.fake()->unique()->word(),
            'lifecycle_state' => 'draft',
        ];
    }
}
