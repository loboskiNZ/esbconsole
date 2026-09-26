<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionalAllocation extends Model
{
    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_INACTIVE,
            self::STATUS_ACTIVE,
            self::STATUS_CLAIMED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_id',
        'label',
        'holder_name',
        'holder_email',
        'status',
        'quantity',
        'claimed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * @return HasMany<CheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function checkedInQuantity(): int
    {
        if ($this->relationLoaded('checkIns')) {
            return (int) $this->checkIns->sum('quantity');
        }

        return (int) $this->checkIns()->sum('quantity');
    }
}
