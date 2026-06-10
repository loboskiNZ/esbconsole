<?php

namespace App\Models;

use Database\Factories\CapabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Capability extends Model
{
    /** @use HasFactory<CapabilityFactory> */
    use HasFactory;

    protected $fillable = [
        'musician_id',
        'instrument_part_id',
    ];

    public function musician(): BelongsTo
    {
        return $this->belongsTo(Musician::class);
    }

    public function instrumentPart(): BelongsTo
    {
        return $this->belongsTo(InstrumentPart::class);
    }
}
