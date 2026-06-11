<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory, HasPublicId;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_READY = 'ready';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'band_id',
        'song_code',
        'name',
        'bpm',
        'description',
        'notes',
        'status',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function cues(): HasMany
    {
        return $this->hasMany(Cue::class)->orderBy('cue_number');
    }

    public function songInstrumentParts(): HasMany
    {
        return $this->hasMany(SongInstrumentPart::class);
    }

    public function charts(): HasMany
    {
        return $this->hasMany(Chart::class);
    }

    public function cuesInPerformanceOrder(): HasMany
    {
        return $this->hasMany(Cue::class)->inPerformanceOrder();
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(ShowPlaylistItem::class);
    }

    public function performanceAssignments(): HasMany
    {
        return $this->hasMany(PerformanceAssignment::class);
    }
}
