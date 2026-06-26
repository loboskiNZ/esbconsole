<?php

namespace App\Models;

use App\Models\Library\Song;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowPlaylistItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'show_id',
        'song_id',
        'position',
        'ableton_pgm',
        'notes',
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
     * @param  Builder<ShowPlaylistItem>  $query
     * @return Builder<ShowPlaylistItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Show, $this>
     */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /**
     * @return BelongsTo<Song, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
