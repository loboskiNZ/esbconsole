<?php

namespace App\Models;

use Database\Factories\RuntimeDispatchItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuntimeDispatchItem extends Model
{
    /** @use HasFactory<RuntimeDispatchItemFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'runtime_dispatch_id',
        'runtime_action_item_id',
        'adapter_key',
        'action_type_code',
        'sort_order',
        'payload',
        'status',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function runtimeDispatch(): BelongsTo
    {
        return $this->belongsTo(RuntimeDispatch::class);
    }

    public function runtimeActionItem(): BelongsTo
    {
        return $this->belongsTo(RuntimeActionItem::class);
    }
}
