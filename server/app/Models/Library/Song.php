<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    protected $table = 'songs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'band_id',
        'song_code',
        'name',
    ];

    public function getConnectionName(): ?string
    {
        return config('portal.library_connection');
    }

    /**
     * @return HasMany<SongInstrumentPart, $this>
     */
    public function songInstrumentParts(): HasMany
    {
        return $this->hasMany(SongInstrumentPart::class);
    }

    /**
     * @return HasMany<Chart, $this>
     */
    public function charts(): HasMany
    {
        return $this->hasMany(Chart::class);
    }
}
