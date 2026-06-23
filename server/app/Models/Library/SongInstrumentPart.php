<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongInstrumentPart extends Model
{
    protected $table = 'song_instrument_parts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'song_id',
        'instrument_part_id',
        'chart_id',
    ];

    public function getConnectionName(): ?string
    {
        return config('portal.library_connection');
    }

    /**
     * @return BelongsTo<Song, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    /**
     * @return BelongsTo<InstrumentPart, $this>
     */
    public function instrumentPart(): BelongsTo
    {
        return $this->belongsTo(InstrumentPart::class);
    }

    /**
     * @return BelongsTo<Chart, $this>
     */
    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }
}
