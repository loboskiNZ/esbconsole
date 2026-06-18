<?php

namespace App\Models;

use App\Enums\FallbackConsoleRecallType;
use App\Enums\SongEffectAssignmentType;
use Database\Factories\SongEffectAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongEffectAssignment extends Model
{
    /** @use HasFactory<SongEffectAssignmentFactory> */
    use HasFactory;
    protected $fillable = [
        'song_id',
        'effect_package_id',
        'priority',
        'assignment_type',
        'enabled',
        'fallback_console_recall_name',
        'fallback_console_recall_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assignment_type' => SongEffectAssignmentType::class,
            'fallback_console_recall_type' => FallbackConsoleRecallType::class,
            'enabled' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function effectPackage(): BelongsTo
    {
        return $this->belongsTo(EffectPackage::class);
    }
}
