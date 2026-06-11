<?php

namespace App\Services;

use App\Models\Cue;
use App\Models\CueAction;
use App\Models\Performance;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeAuditRecord;
use App\Models\RuntimeEvent;
use App\Models\Song;
use Illuminate\Support\Facades\DB;
use Throwable;

class RuntimeEventPlanner
{
    public function __construct(
        private readonly CueActionResolver $cueActionResolver,
    ) {}

    public function plan(RuntimeEvent $runtimeEvent): RuntimeEventPlanResult
    {
        $runtimeEvent->loadMissing([
            'performance.show',
            'runtimeActionPlan.runtimeActionItems',
        ]);

        $existingPlan = $runtimeEvent->runtimeActionPlan;
        if ($existingPlan !== null) {
            return new RuntimeEventPlanResult(
                runtimeEvent: $runtimeEvent,
                runtimeActionPlan: $existingPlan,
                resolutionSucceeded: $existingPlan->cue_id !== null,
                planningSucceeded: true,
            );
        }

        try {
            return DB::transaction(function () use ($runtimeEvent) {
                $this->recordAudit(
                    runtimeEvent: $runtimeEvent,
                    stage: RuntimeAuditRecord::STAGE_EVENT_RECEIVED,
                    message: 'Runtime event received for planning.',
                    context: [
                        'source' => $runtimeEvent->source,
                        'event_type' => $runtimeEvent->event_type,
                        'runtime_identity' => $runtimeEvent->runtime_identity,
                    ],
                );

                $cue = $this->resolveCue($runtimeEvent);

                if ($cue === null) {
                    $runtimeEvent->update(['status' => RuntimeEvent::STATUS_FAILED_RESOLUTION]);

                    $this->recordAudit(
                        runtimeEvent: $runtimeEvent,
                        stage: RuntimeAuditRecord::STAGE_RESOLUTION_FAILED,
                        message: 'Cue resolution failed for runtime identity.',
                        context: [
                            'runtime_identity' => $runtimeEvent->runtime_identity,
                            'song_code' => $runtimeEvent->song_code,
                            'cue_number' => $runtimeEvent->cue_number,
                        ],
                    );

                    return new RuntimeEventPlanResult(
                        runtimeEvent: $runtimeEvent->fresh(),
                        runtimeActionPlan: null,
                        resolutionSucceeded: false,
                        planningSucceeded: false,
                    );
                }

                $runtimeEvent->update(['status' => RuntimeEvent::STATUS_RESOLVED]);

                $this->recordAudit(
                    runtimeEvent: $runtimeEvent,
                    stage: RuntimeAuditRecord::STAGE_CUE_RESOLVED,
                    message: 'Cue resolved from runtime identity.',
                    context: [
                        'runtime_identity' => $runtimeEvent->runtime_identity,
                        'cue_id' => $cue->id,
                        'song_id' => $cue->song_id,
                    ],
                );

                $plan = RuntimeActionPlan::create([
                    'runtime_event_id' => $runtimeEvent->id,
                    'performance_id' => $runtimeEvent->performance_id,
                    'cue_id' => $cue->id,
                    'runtime_identity' => $runtimeEvent->runtime_identity,
                    'status' => RuntimeActionPlan::STATUS_PENDING,
                ]);

                $resolved = $this->cueActionResolver->resolve($cue);

                $cueActionIds = collect($resolved->actions)->pluck('cue_action_id')->all();
                $cueActionsById = CueAction::query()
                    ->whereIn('id', $cueActionIds)
                    ->get()
                    ->keyBy('id');

                foreach ($resolved->actions as $action) {
                    $cueAction = $cueActionsById->get($action['cue_action_id']);

                    $item = RuntimeActionItem::create([
                        'runtime_action_plan_id' => $plan->id,
                        'action_definition_id' => $cueAction->action_definition_id,
                        'action_type_code' => $action['action_type_code'],
                        'action_definition_code' => $action['action_definition_code'],
                        'action_definition_name' => $action['action_definition_name'],
                        'sort_order' => $action['sort_order'],
                        'parameters' => $action['parameters'],
                        'status' => RuntimeActionItem::STATUS_READY,
                    ]);

                    $this->recordAudit(
                        runtimeEvent: $runtimeEvent,
                        stage: RuntimeAuditRecord::STAGE_ACTION_ITEM_CREATED,
                        message: 'Runtime action item created from resolved cue action.',
                        context: [
                            'action_definition_code' => $action['action_definition_code'],
                            'sort_order' => $action['sort_order'],
                        ],
                        runtimeActionPlan: $plan,
                        runtimeActionItem: $item,
                    );
                }

                $plan->update(['status' => RuntimeActionPlan::STATUS_READY]);
                $runtimeEvent->update(['status' => RuntimeEvent::STATUS_PLANNED]);

                $this->recordAudit(
                    runtimeEvent: $runtimeEvent,
                    stage: RuntimeAuditRecord::STAGE_ACTIONS_PLANNED,
                    message: 'Runtime action plan prepared.',
                    context: [
                        'action_item_count' => count($resolved->actions),
                        'runtime_identity' => $runtimeEvent->runtime_identity,
                    ],
                    runtimeActionPlan: $plan,
                );

                return new RuntimeEventPlanResult(
                    runtimeEvent: $runtimeEvent->fresh(),
                    runtimeActionPlan: $plan->fresh(['runtimeActionItems']),
                    resolutionSucceeded: true,
                    planningSucceeded: true,
                );
            });
        } catch (Throwable $exception) {
            $runtimeEvent->update(['status' => RuntimeEvent::STATUS_FAILED_PLANNING]);

            $this->recordAudit(
                runtimeEvent: $runtimeEvent,
                stage: RuntimeAuditRecord::STAGE_PLANNING_FAILED,
                message: 'Runtime action planning failed.',
                context: [
                    'error' => $exception->getMessage(),
                ],
            );

            return new RuntimeEventPlanResult(
                runtimeEvent: $runtimeEvent->fresh(),
                runtimeActionPlan: $runtimeEvent->runtimeActionPlan,
                resolutionSucceeded: false,
                planningSucceeded: false,
            );
        }
    }

    private function resolveCue(RuntimeEvent $runtimeEvent): ?Cue
    {
        $performance = $runtimeEvent->performance;

        [$songCode, $cueNumber] = $this->resolveIdentityParts($runtimeEvent);

        $song = Song::query()
            ->where('band_id', $performance->band_id)
            ->where('song_code', $songCode)
            ->first();

        if ($song === null) {
            return null;
        }

        if ($performance->show_id !== null) {
            $songInShow = $performance->show
                ->playlistItems()
                ->where('song_id', $song->id)
                ->exists();

            if (! $songInShow) {
                return null;
            }
        }

        return Cue::query()
            ->where('song_id', $song->id)
            ->where('cue_number', $cueNumber)
            ->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveIdentityParts(RuntimeEvent $runtimeEvent): array
    {
        if ($runtimeEvent->song_code !== '' && $runtimeEvent->cue_number !== '') {
            return [$runtimeEvent->song_code, $runtimeEvent->cue_number];
        }

        if (! str_contains($runtimeEvent->runtime_identity, '.')) {
            return ['', ''];
        }

        [$songCode, $cueNumber] = explode('.', $runtimeEvent->runtime_identity, 2);

        return [$songCode, $cueNumber];
    }

    private function recordAudit(
        RuntimeEvent $runtimeEvent,
        string $stage,
        string $message,
        ?array $context = null,
        ?RuntimeActionPlan $runtimeActionPlan = null,
        ?RuntimeActionItem $runtimeActionItem = null,
    ): void {
        RuntimeAuditRecord::create([
            'runtime_event_id' => $runtimeEvent->id,
            'runtime_action_plan_id' => $runtimeActionPlan?->id,
            'runtime_action_item_id' => $runtimeActionItem?->id,
            'stage' => $stage,
            'message' => $message,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
