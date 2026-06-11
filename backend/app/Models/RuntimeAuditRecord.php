<?php

namespace App\Models;

use Database\Factories\RuntimeAuditRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuntimeAuditRecord extends Model
{
    /** @use HasFactory<RuntimeAuditRecordFactory> */
    use HasFactory;

    public const STAGE_EVENT_RECEIVED = 'event_received';

    public const STAGE_CUE_RESOLVED = 'cue_resolved';

    public const STAGE_RESOLUTION_FAILED = 'resolution_failed';

    public const STAGE_ACTIONS_PLANNED = 'actions_planned';

    public const STAGE_PLANNING_FAILED = 'planning_failed';

    public const STAGE_ACTION_ITEM_CREATED = 'action_item_created';

    public const STAGE_ADAPTER_RESULT_FUTURE = 'adapter_result_future';

    public const STAGE_DISPATCH_CREATED = 'dispatch_created';

    public const STAGE_DISPATCH_ITEM_CREATED = 'dispatch_item_created';

    public const STAGE_DISPATCH_BUILD_SKIPPED = 'dispatch_build_skipped';

    public const STAGE_DISPATCH_BUILD_FAILED = 'dispatch_build_failed';

    protected $fillable = [
        'runtime_event_id',
        'runtime_action_plan_id',
        'runtime_action_item_id',
        'stage',
        'message',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function runtimeEvent(): BelongsTo
    {
        return $this->belongsTo(RuntimeEvent::class);
    }

    public function runtimeActionPlan(): BelongsTo
    {
        return $this->belongsTo(RuntimeActionPlan::class);
    }

    public function runtimeActionItem(): BelongsTo
    {
        return $this->belongsTo(RuntimeActionItem::class);
    }
}
