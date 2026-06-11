<?php

namespace Database\Factories;

use App\Models\RuntimeAuditRecord;
use App\Models\RuntimeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeAuditRecord>
 */
class RuntimeAuditRecordFactory extends Factory
{
    protected $model = RuntimeAuditRecord::class;

    public function definition(): array
    {
        return [
            'runtime_event_id' => RuntimeEvent::factory(),
            'runtime_action_plan_id' => null,
            'runtime_action_item_id' => null,
            'stage' => RuntimeAuditRecord::STAGE_EVENT_RECEIVED,
            'message' => 'Runtime event received.',
            'context' => null,
            'occurred_at' => now(),
        ];
    }
}
