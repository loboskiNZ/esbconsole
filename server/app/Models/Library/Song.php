<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_READY = 'ready';

    public const STATUS_ARCHIVED = 'archived';

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
        'lyrics',
        'director_notes',
        'mood_intention',
        'performance_feel',
        'arrangement_comments',
        'reference_url',
        'reference_title',
        'reference_notes',
        'spotify_url',
        'youtube_url',
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

    /**
     * @return HasMany<SongAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(SongAsset::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_READY => 'Ready',
            self::STATUS_ARCHIVED => 'Archived',
            default => str((string) ($this->status ?? self::STATUS_DRAFT))->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * @param  Builder<Song>  $query
     * @return Builder<Song>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    /**
     * @param  Builder<Song>  $query
     * @return Builder<Song>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }
}
