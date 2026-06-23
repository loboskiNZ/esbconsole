<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    protected $table = 'songs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'band_id',
        'song_code',
        'name',
        'bpm',
        'time_signature_id',
        'musical_key_id',
        'mood_id',
        'genre',
        'style',
        'tempo_feel',
        'count_in',
        'description',
        'notes',
        'director_notes',
        'mood_intention',
        'performance_feel',
        'arrangement_comments',
        'reference_url',
        'reference_title',
        'reference_notes',
        'status',
    ];

    public function getConnectionName(): ?string
    {
        return config('portal.library_connection');
    }

    public function timeSignature(): BelongsTo
    {
        return $this->belongsTo(TimeSignature::class);
    }

    public function musicalKey(): BelongsTo
    {
        return $this->belongsTo(MusicalKey::class);
    }

    public function mood(): BelongsTo
    {
        return $this->belongsTo(SongMood::class, 'mood_id');
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
