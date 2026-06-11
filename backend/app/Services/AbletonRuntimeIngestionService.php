<?php

namespace App\Services;

use App\Models\Performance;
use App\Models\RuntimeEvent;

class AbletonRuntimeIngestionService
{
    public const SOURCE_ABLETON = 'ABLETON';

    public const EVENT_TYPE_CUE_ENTER = 'CUE_ENTER';

    public function __construct(
        private readonly RuntimeEventPlanner $runtimeEventPlanner,
    ) {}

    public function ingest(
        int $performanceId,
        string $runtimeIdentity,
        string $source = self::SOURCE_ABLETON,
        string $eventType = self::EVENT_TYPE_CUE_ENTER,
        ?array $payload = null,
    ): AbletonRuntimeIngestionResult {
        $identity = RuntimeIdentityValidator::parse($runtimeIdentity);

        $performance = Performance::query()->findOrFail($performanceId);

        $runtimeEvent = RuntimeEvent::create([
            'performance_id' => $performance->id,
            'source' => $source,
            'event_type' => $eventType,
            'runtime_identity' => $runtimeIdentity,
            'song_code' => $identity['song_code'],
            'cue_number' => $identity['cue_number'],
            'status' => RuntimeEvent::STATUS_RECEIVED,
            'received_at' => now(),
            'payload' => $payload,
        ]);

        $planResult = $this->runtimeEventPlanner->plan($runtimeEvent);

        $runtimeEvent = $planResult->runtimeEvent->load([
            'runtimeActionPlan.runtimeActionItems',
            'runtimeAuditRecords',
        ]);

        $planResult->runtimeActionPlan?->loadMissing('runtimeActionItems');

        return new AbletonRuntimeIngestionResult(
            runtimeEvent: $runtimeEvent,
            planResult: $planResult,
        );
    }
}
