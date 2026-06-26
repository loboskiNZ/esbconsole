<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Performance extends Model
{
    public const TYPE_REHEARSAL = 'rehearsal';

    public const TYPE_LIVE = 'live';

    public const STATUS_NOT_CONFIRMED = 'not_confirmed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function validStatuses(): array
    {
        return [
            self::STATUS_NOT_CONFIRMED,
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'band_id',
        'show_id',
        'performance_type',
        'status',
        'venue',
        'location_name',
        'location_address',
        'performance_date',
        'prep_time',
        'performance_time',
        'performance_duration_minutes',
        'packup_time',
        'briefing_notes',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performance_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Band, $this>
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * @return BelongsTo<Show, $this>
     */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /**
     * @return HasMany<PerformanceAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PerformanceAssignment::class);
    }

    public function typeLabel(): string
    {
        return match ($this->performance_type) {
            self::TYPE_LIVE => 'Live',
            self::TYPE_REHEARSAL => 'Rehearsal',
            default => str($this->performance_type ?? self::TYPE_REHEARSAL)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_NOT_CONFIRMED => 'Not confirmed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => str($this->status ?? self::STATUS_NOT_CONFIRMED)->replace('_', ' ')->title()->toString(),
        };
    }

    public function locationNameLabel(): string
    {
        $name = $this->location_name ?? $this->venue;

        return is_string($name) && trim($name) !== '' ? trim($name) : '—';
    }

    public function briefingNotesLabel(): ?string
    {
        $notes = $this->briefing_notes ?? $this->notes;

        if (! is_string($notes)) {
            return null;
        }

        $trimmed = trim($notes);

        return $trimmed === '' ? null : $trimmed;
    }

    public function formattedPerformanceDate(): string
    {
        return $this->performance_date?->format('D j M Y') ?? '—';
    }

    public function formattedTime(?string $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        return substr($value, 0, 5);
    }

    public function durationLabel(): string
    {
        if ($this->performance_duration_minutes === null) {
            return '—';
        }

        return $this->performance_duration_minutes.' min';
    }
}
