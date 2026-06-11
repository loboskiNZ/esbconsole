<?php

namespace App\Models;

use Database\Factories\RuntimeActionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RuntimeActionItem extends Model
{
    /** @use HasFactory<RuntimeActionItemFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'runtime_action_plan_id',
        'action_definition_id',
        'action_type_code',
        'action_definition_code',
        'action_definition_name',
        'sort_order',
        'parameters',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
        ];
    }

    public function runtimeActionPlan(): BelongsTo
    {
        return $this->belongsTo(RuntimeActionPlan::class);
    }

    public function actionDefinition(): BelongsTo
    {
        return $this->belongsTo(ActionDefinition::class);
    }

    public function runtimeAuditRecords(): HasMany
    {
        return $this->hasMany(RuntimeAuditRecord::class);
    }

    public function runtimeDispatchItem(): HasOne
    {
        return $this->hasOne(RuntimeDispatchItem::class);
    }
}
