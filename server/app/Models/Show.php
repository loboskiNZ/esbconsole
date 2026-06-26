<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Show extends Model
{
    public const STATE_DRAFT = 'draft';

    public const STATE_PLANNED = 'planned';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'band_id',
        'ableton_show_file_id',
        'name',
        'description',
        'lifecycle_state',
        'scheduled_at',
        'venue_location',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
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
     * @return BelongsTo<AbletonShowFile, $this>
     */
    public function abletonShowFile(): BelongsTo
    {
        return $this->belongsTo(AbletonShowFile::class);
    }

    public function statusLabel(): string
    {
        return match ($this->lifecycle_state) {
            self::STATE_PLANNED => 'Planned',
            self::STATE_DRAFT => 'Draft',
            default => str($this->lifecycle_state ?? 'draft')->replace('_', ' ')->title()->toString(),
        };
    }

    public function scheduleLabel(): ?string
    {
        return $this->scheduled_at?->format('d M Y H:i');
    }
}
