<?php

namespace Database\Factories;

use App\Models\RuntimeActionPlan;
use App\Models\RuntimeDispatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeDispatch>
 */
class RuntimeDispatchFactory extends Factory
{
    protected $model = RuntimeDispatch::class;

    public function definition(): array
    {
        $plan = RuntimeActionPlan::factory()->create([
            'status' => 'ready',
        ]);

        return [
            'runtime_action_plan_id' => $plan->id,
            'performance_id' => $plan->performance_id,
            'status' => RuntimeDispatch::STATUS_READY,
        ];
    }
}
