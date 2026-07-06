<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'time_signature_id',
        'musical_key_id',
        'mood_id',
        'genre',
        'style',
        'tempo_feel',
        'count_in',
        'description',
        'notes',
        'lyrics',
        'director_notes',
        'mood_intention',
        'performance_feel',
        'arrangement_comments',
        'reference_url',
        'reference_title',
        'reference_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'bpm' => 'integer',
            'count_in' => 'integer',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
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

    public function songEffectAssignments(): HasMany
    {
        return $this->hasMany(SongEffectAssignment::class)->orderBy('priority');
    }

    public function effectPackages(): BelongsToMany
    {
        return $this->belongsToMany(EffectPackage::class, 'song_effect_assignments')
            ->withPivot([
                'priority',
                'assignment_type',
                'enabled',
                'fallback_console_recall_name',
                'fallback_console_recall_type',
                'notes',
            ])
            ->withTimestamps()
            ->orderBy('song_effect_assignments.priority');
    }
}
