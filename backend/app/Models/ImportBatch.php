<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasPublicId;

    public const STATUS_DRY_RUN = 'dry_run';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'band_id',
        'legacy_setlist_id',
        'status',
        'manifest_json',
        'report_json',
        'started_at',
        'completed_at',
        'initiated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'manifest_json' => 'array',
            'report_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function entityMappings(): HasMany
    {
        return $this->hasMany(ImportEntityMapping::class);
    }
}
