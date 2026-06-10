<?php

namespace Database\Factories;

use App\Models\AbletonShowFile;
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
            'description' => null,
            'lifecycle_state' => 'draft',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Show $show) {
            if (! $show->ableton_show_file_id) {
                $file = AbletonShowFile::factory()->create(['band_id' => $show->band_id]);
                $show->forceFill(['ableton_show_file_id' => $file->id])->save();
            }
        });
    }

    public function forBand(Band $band): static
    {
        return $this->state(fn () => ['band_id' => $band->id]);
    }
}
