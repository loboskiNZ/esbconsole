<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\PerformanceAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceAssignment extends Model
{
    /** @use HasFactory<PerformanceAssignmentFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'performance_id',
        'musician_id',
        'instrument_part_id',
        'song_id',
        'cue_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function musician(): BelongsTo
    {
        return $this->belongsTo(Musician::class);
    }

    public function instrumentPart(): BelongsTo
    {
        return $this->belongsTo(InstrumentPart::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function cue(): BelongsTo
    {
        return $this->belongsTo(Cue::class);
    }
}
