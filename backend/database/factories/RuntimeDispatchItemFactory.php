<?php

namespace Database\Factories;

use App\Models\RuntimeActionItem;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeDispatchItem>
 */
class RuntimeDispatchItemFactory extends Factory
{
    protected $model = RuntimeDispatchItem::class;

    public function definition(): array
    {
        $actionItem = RuntimeActionItem::factory()->create();

        return [
            'runtime_dispatch_id' => RuntimeDispatch::factory(),
            'runtime_action_item_id' => $actionItem->id,
            'adapter_key' => 'x32',
            'action_type_code' => $actionItem->action_type_code,
            'sort_order' => $actionItem->sort_order,
            'payload' => [
                'action_type_code' => $actionItem->action_type_code,
                'action_definition_code' => $actionItem->action_definition_code,
                'action_definition_name' => $actionItem->action_definition_name,
                'parameters' => $actionItem->parameters ?? [],
            ],
            'status' => RuntimeDispatchItem::STATUS_READY,
            'attempts' => 0,
            'last_error' => null,
        ];
    }
}
