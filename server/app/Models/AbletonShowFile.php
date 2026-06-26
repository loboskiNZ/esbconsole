<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AbletonShowFile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'band_id',
        'name',
        'storage_reference',
        'checksum',
        'notes',
    ];

    /**
     * @return BelongsTo<Band, $this>
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * @return HasOne<Show, $this>
     */
    public function show(): HasOne
    {
        return $this->hasOne(Show::class);
    }
}
