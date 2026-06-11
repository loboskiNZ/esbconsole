<?php

namespace App\Models;

use Database\Factories\RuntimeDispatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuntimeDispatch extends Model
{
    /** @use HasFactory<RuntimeDispatchFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_DISPATCHING = 'dispatching';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'runtime_action_plan_id',
        'performance_id',
        'status',
    ];

    public function runtimeActionPlan(): BelongsTo
    {
        return $this->belongsTo(RuntimeActionPlan::class);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function runtimeDispatchItems(): HasMany
    {
        return $this->hasMany(RuntimeDispatchItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
