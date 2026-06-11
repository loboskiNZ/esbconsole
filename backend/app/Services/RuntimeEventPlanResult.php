<?php

namespace App\Services;

use App\Models\RuntimeActionPlan;
use App\Models\RuntimeEvent;

readonly class RuntimeEventPlanResult
{
    public function __construct(
        public RuntimeEvent $runtimeEvent,
        public ?RuntimeActionPlan $runtimeActionPlan,
        public bool $resolutionSucceeded,
        public bool $planningSucceeded,
    ) {}

    public function toArray(): array
    {
        return [
            'runtime_event_id' => $this->runtimeEvent->id,
            'runtime_event_status' => $this->runtimeEvent->status,
            'runtime_action_plan_id' => $this->runtimeActionPlan?->id,
            'runtime_action_plan_status' => $this->runtimeActionPlan?->status,
            'resolution_succeeded' => $this->resolutionSucceeded,
            'planning_succeeded' => $this->planningSucceeded,
        ];
    }
}
