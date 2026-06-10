<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\AbletonShowFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AbletonShowFile extends Model
{
    /** @use HasFactory<AbletonShowFileFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'name',
        'storage_reference',
        'checksum',
        'notes',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function show(): HasOne
    {
        return $this->hasOne(Show::class);
    }
}
