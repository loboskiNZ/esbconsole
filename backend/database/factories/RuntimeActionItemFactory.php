<?php

namespace Database\Factories;

use App\Models\ActionDefinition;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeActionItem>
 */
class RuntimeActionItemFactory extends Factory
{
    protected $model = RuntimeActionItem::class;

    public function definition(): array
    {
        $definition = ActionDefinition::factory()->create();

        return [
            'runtime_action_plan_id' => RuntimeActionPlan::factory(),
            'action_definition_id' => $definition->id,
            'action_type_code' => 'X32_SCENE',
            'action_definition_code' => $definition->code,
            'action_definition_name' => $definition->name,
            'sort_order' => 0,
            'parameters' => null,
            'status' => RuntimeActionItem::STATUS_READY,
        ];
    }
}
