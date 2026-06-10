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
        static $codeCounter = 1;

        return [
            'band_id' => Band::factory(),
            'song_code' => str_pad((string) ($codeCounter++ % 999 ?: 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Test Song '.fake()->unique()->word(),
            'bpm' => fake()->numberBetween(80, 140),
            'description' => null,
            'notes' => null,
            'status' => Song::STATUS_DRAFT,
        ];
    }

    public function forBand(Band $band): static
    {
        return $this->state(function (array $attributes) use ($band) {
            $nextCode = str_pad(
                (string) (($band->songs()->count() % 999) + 1),
                3,
                '0',
                STR_PAD_LEFT,
            );

            return [
                'band_id' => $band->id,
                'song_code' => $nextCode,
            ];
        });
    }
}
