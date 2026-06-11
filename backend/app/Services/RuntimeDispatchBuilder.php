<?php

namespace App\Services;

use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeAuditRecord;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RuntimeDispatchBuilder
{
    public function __construct(
        private readonly AdapterKeyResolver $adapterKeyResolver,
    ) {}

    public function build(RuntimeActionPlan $runtimeActionPlan): RuntimeDispatchBuildResult
    {
        $runtimeActionPlan->loadMissing([
            'runtimeActionItems',
            'runtimeDispatch.runtimeDispatchItems',
            'runtimeEvent',
        ]);

        $existingDispatch = $runtimeActionPlan->runtimeDispatch;
        if ($existingDispatch !== null) {
            $this->recordAudit(
                runtimeActionPlan: $runtimeActionPlan,
                stage: RuntimeAuditRecord::STAGE_DISPATCH_BUILD_SKIPPED,
                message: 'Runtime dispatch already exists for action plan.',
                context: [
                    'runtime_dispatch_id' => $existingDispatch->id,
                ],
            );

            return new RuntimeDispatchBuildResult(
                runtimeDispatch: $existingDispatch,
                created: false,
            );
        }

        if ($runtimeActionPlan->status !== RuntimeActionPlan::STATUS_READY) {
            $this->recordAudit(
                runtimeActionPlan: $runtimeActionPlan,
                stage: RuntimeAuditRecord::STAGE_DISPATCH_BUILD_FAILED,
                message: 'Runtime dispatch cannot be built from a non-ready action plan.',
                context: [
                    'plan_status' => $runtimeActionPlan->status,
                ],
            );

            throw new InvalidArgumentException(
                'Runtime dispatch can only be built from a ready RuntimeActionPlan.',
            );
        }

        return DB::transaction(function () use ($runtimeActionPlan) {
            $dispatch = RuntimeDispatch::create([
                'runtime_action_plan_id' => $runtimeActionPlan->id,
                'performance_id' => $runtimeActionPlan->performance_id,
                'status' => RuntimeDispatch::STATUS_READY,
            ]);

            foreach ($runtimeActionPlan->runtimeActionItems as $actionItem) {
                $dispatchItem = $this->createDispatchItem($dispatch, $actionItem);

                $this->recordAudit(
                    runtimeActionPlan: $runtimeActionPlan,
                    stage: RuntimeAuditRecord::STAGE_DISPATCH_ITEM_CREATED,
                    message: 'Runtime dispatch item created from action item.',
                    context: [
                        'runtime_dispatch_item_id' => $dispatchItem->id,
                        'adapter_key' => $dispatchItem->adapter_key,
                        'sort_order' => $dispatchItem->sort_order,
                    ],
                    runtimeActionItem: $actionItem,
                );
            }

            $this->recordAudit(
                runtimeActionPlan: $runtimeActionPlan,
                stage: RuntimeAuditRecord::STAGE_DISPATCH_CREATED,
                message: 'Runtime dispatch prepared from action plan.',
                context: [
                    'runtime_dispatch_id' => $dispatch->id,
                    'dispatch_item_count' => $runtimeActionPlan->runtimeActionItems->count(),
                ],
            );

            return new RuntimeDispatchBuildResult(
                runtimeDispatch: $dispatch->fresh(['runtimeDispatchItems']),
                created: true,
            );
        });
    }

    private function createDispatchItem(RuntimeDispatch $dispatch, RuntimeActionItem $actionItem): RuntimeDispatchItem
    {
        return RuntimeDispatchItem::create([
            'runtime_dispatch_id' => $dispatch->id,
            'runtime_action_item_id' => $actionItem->id,
            'adapter_key' => $this->adapterKeyResolver->resolve($actionItem->action_type_code),
            'action_type_code' => $actionItem->action_type_code,
            'sort_order' => $actionItem->sort_order,
            'payload' => $this->buildPayloadSnapshot($actionItem),
            'status' => RuntimeDispatchItem::STATUS_READY,
            'attempts' => 0,
            'last_error' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadSnapshot(RuntimeActionItem $actionItem): array
    {
        return [
            'action_type_code' => $actionItem->action_type_code,
            'action_definition_code' => $actionItem->action_definition_code,
            'action_definition_name' => $actionItem->action_definition_name,
            'parameters' => $actionItem->parameters ?? [],
        ];
    }

    private function recordAudit(
        RuntimeActionPlan $runtimeActionPlan,
        string $stage,
        string $message,
        ?array $context = null,
        ?RuntimeActionItem $runtimeActionItem = null,
    ): void {
        RuntimeAuditRecord::create([
            'runtime_event_id' => $runtimeActionPlan->runtimeEvent?->id,
            'runtime_action_plan_id' => $runtimeActionPlan->id,
            'runtime_action_item_id' => $runtimeActionItem?->id,
            'stage' => $stage,
            'message' => $message,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
