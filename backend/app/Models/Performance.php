<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\PerformanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Performance extends Model
{
    /** @use HasFactory<PerformanceFactory> */
    use HasFactory, HasPublicId;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_SOUNDCHECK = 'soundcheck';

    public const STATUS_READY = 'ready';

    public const STATUS_LIVE = 'live';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'band_id',
        'show_id',
        'venue',
        'performance_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'performance_date' => 'date',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function performanceAssignments(): HasMany
    {
        return $this->hasMany(PerformanceAssignment::class);
    }

    public function soundcheck(): HasOne
    {
        return $this->hasOne(Soundcheck::class);
    }

    public function readiness(): HasOne
    {
        return $this->hasOne(Readiness::class);
    }

    public function runtimeEvents(): HasMany
    {
        return $this->hasMany(RuntimeEvent::class);
    }

    public function runtimeActionPlans(): HasMany
    {
        return $this->hasMany(RuntimeActionPlan::class);
    }
}
