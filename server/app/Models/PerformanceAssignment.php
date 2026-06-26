<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceAssignment extends Model
{
    public const AVAILABILITY_UNKNOWN = 'unknown';

    public const AVAILABILITY_AVAILABLE = 'available';

    public const AVAILABILITY_UNAVAILABLE = 'unavailable';

    public const AVAILABILITY_MAYBE = 'maybe';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_id',
        'musician_id',
        'instrument_part_id',
        'song_id',
        'cue_id',
        'active',
        'availability_status',
        'availability_notes',
    ];

    /**
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * @return BelongsTo<Musician, $this>
     */
    public function musician(): BelongsTo
    {
        return $this->belongsTo(Musician::class);
    }

    public function availabilityStatusLabel(): string
    {
        return match ($this->availability_status) {
            self::AVAILABILITY_AVAILABLE => 'Available',
            self::AVAILABILITY_UNAVAILABLE => 'Unavailable',
            self::AVAILABILITY_MAYBE => 'Maybe',
            self::AVAILABILITY_UNKNOWN => 'Unknown',
            default => str($this->availability_status ?? self::AVAILABILITY_UNKNOWN)->replace('_', ' ')->title()->toString(),
        };
    }
}
