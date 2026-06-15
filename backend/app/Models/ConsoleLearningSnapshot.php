<?php

namespace App\Models;

use App\Enums\ConsoleLearningStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ConsoleLearningSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsoleLearningSnapshot extends Model
{
    /** @use HasFactory<ConsoleLearningSnapshotFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'show_id',
        'integration_device_id',
        'requested_scene_number',
        'learning_status',
        'learned_summary_json',
        'raw_snapshot_json',
        'warnings_json',
        'errors_json',
        'learned_at',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'learning_status' => ConsoleLearningStatus::class,
            'learned_summary_json' => 'array',
            'raw_snapshot_json' => 'array',
            'warnings_json' => 'array',
            'errors_json' => 'array',
            'learned_at' => 'datetime',
            'saved_at' => 'datetime',
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

    public function integrationDevice(): BelongsTo
    {
        return $this->belongsTo(IntegrationDevice::class);
    }

    public function showConsoleBaseline(): HasOne
    {
        return $this->hasOne(ShowConsoleBaseline::class, 'source_snapshot_id');
    }
}
