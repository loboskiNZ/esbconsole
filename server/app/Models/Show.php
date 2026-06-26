<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Show extends Model
{
    public const STATE_DRAFT = 'draft';

    public const STATE_PLANNED = 'planned';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'band_id',
        'ableton_show_file_id',
        'name',
        'description',
        'lifecycle_state',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Show>  $query
     * @return Builder<Show>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Show>  $query
     * @return Builder<Show>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * @return BelongsTo<Band, $this>
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * @return BelongsTo<AbletonShowFile, $this>
     */
    public function abletonShowFile(): BelongsTo
    {
        return $this->belongsTo(AbletonShowFile::class);
    }

    /**
     * @return HasMany<ShowPlaylistItem, $this>
     */
    public function playlistItems(): HasMany
    {
        return $this->hasMany(ShowPlaylistItem::class);
    }

    /**
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    public function statusLabel(): string
    {
        return match ($this->lifecycle_state) {
            self::STATE_PLANNED => 'Planned',
            self::STATE_DRAFT => 'Draft',
            default => str($this->lifecycle_state ?? 'draft')->replace('_', ' ')->title()->toString(),
        };
    }
}
