<?php

namespace App\Services;

use App\Models\RuntimeEvent;

readonly class AbletonRuntimeIngestionResult
{
    public function __construct(
        public RuntimeEvent $runtimeEvent,
        public RuntimeEventPlanResult $planResult,
    ) {}

    public function toArray(): array
    {
        $plan = $this->planResult->runtimeActionPlan;

        return [
            'runtime_event' => [
                'id' => $this->runtimeEvent->id,
                'performance_id' => $this->runtimeEvent->performance_id,
                'source' => $this->runtimeEvent->source,
                'event_type' => $this->runtimeEvent->event_type,
                'runtime_identity' => $this->runtimeEvent->runtime_identity,
                'song_code' => $this->runtimeEvent->song_code,
                'cue_number' => $this->runtimeEvent->cue_number,
                'status' => $this->runtimeEvent->status,
                'received_at' => $this->runtimeEvent->received_at?->toIso8601String(),
                'payload' => $this->runtimeEvent->payload,
            ],
            'plan' => $plan ? [
                'id' => $plan->id,
                'status' => $plan->status,
                'runtime_identity' => $plan->runtime_identity,
                'cue_id' => $plan->cue_id,
                'action_items' => $plan->runtimeActionItems->map(fn ($item) => [
                    'id' => $item->id,
                    'action_type_code' => $item->action_type_code,
                    'action_definition_code' => $item->action_definition_code,
                    'action_definition_name' => $item->action_definition_name,
                    'sort_order' => $item->sort_order,
                    'parameters' => $item->parameters,
                    'status' => $item->status,
                ])->values()->all(),
            ] : null,
            'resolution_succeeded' => $this->planResult->resolutionSucceeded,
            'planning_succeeded' => $this->planResult->planningSucceeded,
        ];
    }
}
