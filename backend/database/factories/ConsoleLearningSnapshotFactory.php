<?php

namespace Database\Factories;

use App\Enums\ConsoleLearningStatus;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationDevice;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsoleLearningSnapshot>
 */
class ConsoleLearningSnapshotFactory extends Factory
{
    protected $model = ConsoleLearningSnapshot::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'show_id' => Show::factory(),
            'integration_device_id' => IntegrationDevice::factory(),
            'requested_scene_number' => '01',
            'learning_status' => ConsoleLearningStatus::Review,
            'learned_summary_json' => ['channels' => []],
            'raw_snapshot_json' => ['transport' => 'fake_fixture'],
            'warnings_json' => [],
            'errors_json' => [],
            'learned_at' => now(),
            'saved_at' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'learning_status' => ConsoleLearningStatus::Failed,
            'learned_summary_json' => null,
            'raw_snapshot_json' => null,
            'errors_json' => ['Simulated failure'],
            'learned_at' => now(),
        ]);
    }
}
