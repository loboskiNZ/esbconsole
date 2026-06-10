<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ReadinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Readiness extends Model
{
    /** @use HasFactory<ReadinessFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'readiness_records';

    public const STATUS_NOT_READY = 'not_ready';

    public const STATUS_WARNING = 'warning';

    public const STATUS_READY = 'ready';

    protected $fillable = [
        'performance_id',
        'status',
        'notes',
    ];

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }
}
