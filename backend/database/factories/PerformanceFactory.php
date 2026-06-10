<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\Performance;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Performance>
 */
class PerformanceFactory extends Factory
{
    protected $model = Performance::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'show_id' => Show::factory(),
            'venue' => fake()->city(),
            'performance_date' => fake()->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
            'status' => Performance::STATUS_PLANNED,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Performance $performance) {
            if ($performance->show && $performance->band_id !== $performance->show->band_id) {
                $performance->forceFill(['band_id' => $performance->show->band_id])->save();
            }
        });
    }

    public function forShow(Show $show): static
    {
        return $this->state(fn () => [
            'band_id' => $show->band_id,
            'show_id' => $show->id,
        ]);
    }
}
