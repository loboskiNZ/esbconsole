<?php

namespace Database\Factories;

use App\Models\Cue;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cue>
 */
class CueFactory extends Factory
{
    protected $model = Cue::class;

    public function definition(): array
    {
        static $cueCounter = 1;

        return [
            'song_id' => Song::factory(),
            'cue_number' => str_pad((string) ($cueCounter++ % 1000), 3, '0', STR_PAD_LEFT),
            'sequence_order' => static fn (array $attributes) => (int) $attributes['cue_number'],
            'name' => fake()->word(),
            'description' => null,
            'notes' => null,
        ];
    }

    public function preparation(): static
    {
        return $this->state(fn () => [
            'cue_number' => '000',
            'sequence_order' => 0,
            'name' => 'Preparation',
        ]);
    }
}
