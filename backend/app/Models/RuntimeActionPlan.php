<?php

namespace App\Models;

use Database\Factories\RuntimeActionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuntimeActionPlan extends Model
{
    /** @use HasFactory<RuntimeActionPlanFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'runtime_event_id',
        'performance_id',
        'cue_id',
        'runtime_identity',
        'status',
    ];

    public function runtimeEvent(): BelongsTo
    {
        return $this->belongsTo(RuntimeEvent::class);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function cue(): BelongsTo
    {
        return $this->belongsTo(Cue::class);
    }

    public function runtimeActionItems(): HasMany
    {
        return $this->hasMany(RuntimeActionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function runtimeAuditRecords(): HasMany
    {
        return $this->hasMany(RuntimeAuditRecord::class);
    }
}
