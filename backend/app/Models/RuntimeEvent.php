<?php

namespace App\Models;

use Database\Factories\RuntimeEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RuntimeEvent extends Model
{
    /** @use HasFactory<RuntimeEventFactory> */
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_FAILED_RESOLUTION = 'failed_resolution';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_FAILED_PLANNING = 'failed_planning';

    protected $fillable = [
        'performance_id',
        'source',
        'event_type',
        'runtime_identity',
        'song_code',
        'cue_number',
        'status',
        'received_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function runtimeActionPlan(): HasOne
    {
        return $this->hasOne(RuntimeActionPlan::class);
    }

    public function runtimeAuditRecords(): HasMany
    {
        return $this->hasMany(RuntimeAuditRecord::class);
    }
}
