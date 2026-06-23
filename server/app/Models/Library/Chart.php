<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chart extends Model
{
    protected $table = 'charts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'song_id',
        'title',
        'original_filename',
        'storage_reference',
        'checksum',
        'mime_type',
        'file_size',
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
     * @return HasMany<SongInstrumentPart, $this>
     */
    public function songInstrumentParts(): HasMany
    {
        return $this->hasMany(SongInstrumentPart::class);
    }
}
