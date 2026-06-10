<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ChartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chart extends Model
{
    /** @use HasFactory<ChartFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'song_instrument_part_id',
        'title',
        'storage_reference',
        'checksum',
        'notes',
    ];

    public function songInstrumentPart(): BelongsTo
    {
        return $this->belongsTo(SongInstrumentPart::class);
    }
}
