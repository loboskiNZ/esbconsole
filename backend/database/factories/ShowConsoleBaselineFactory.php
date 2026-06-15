<?php

namespace Database\Factories;

use App\Enums\ConsoleType;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowConsoleBaseline>
 */
class ShowConsoleBaselineFactory extends Factory
{
    protected $model = ShowConsoleBaseline::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'show_id' => Show::factory(),
            'source_snapshot_id' => ConsoleLearningSnapshot::factory(),
            'baseline_name' => 'Scene 01 Baseline',
            'console_type' => ConsoleType::X32,
            'baseline_json' => ['channels' => []],
            'active' => true,
            'saved_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
