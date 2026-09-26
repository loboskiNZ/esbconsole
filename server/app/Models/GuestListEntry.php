<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestListEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'performance_id',
        'guest_name',
        'email',
        'quantity',
        'category',
        'notes',
        'created_by',
    ];

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
