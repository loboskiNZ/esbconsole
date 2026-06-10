<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SongInstrumentPartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongInstrumentPart extends Model
{
    /** @use HasFactory<SongInstrumentPartFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'song_id',
        'instrument_part_id',
        'notes',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function instrumentPart(): BelongsTo
    {
        return $this->belongsTo(InstrumentPart::class);
    }

    public function charts(): HasMany
    {
        return $this->hasMany(Chart::class);
    }

    public function snippets(): HasMany
    {
        return $this->hasMany(Snippet::class);
    }
}
