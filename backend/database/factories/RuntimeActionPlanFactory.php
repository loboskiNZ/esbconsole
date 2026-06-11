<?php

namespace Database\Factories;

use App\Models\RuntimeActionPlan;
use App\Models\RuntimeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeActionPlan>
 */
class RuntimeActionPlanFactory extends Factory
{
    protected $model = RuntimeActionPlan::class;

    public function definition(): array
    {
        $event = RuntimeEvent::factory()->create();

        return [
            'runtime_event_id' => $event->id,
            'performance_id' => $event->performance_id,
            'cue_id' => null,
            'runtime_identity' => $event->runtime_identity,
            'status' => RuntimeActionPlan::STATUS_PENDING,
        ];
    }
}
