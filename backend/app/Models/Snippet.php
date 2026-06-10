<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SnippetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snippet extends Model
{
    /** @use HasFactory<SnippetFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'song_instrument_part_id',
        'cue_id',
        'title',
        'storage_reference',
        'checksum',
        'notes',
    ];

    public function songInstrumentPart(): BelongsTo
    {
        return $this->belongsTo(SongInstrumentPart::class);
    }

    public function cue(): BelongsTo
    {
        return $this->belongsTo(Cue::class);
    }
}
