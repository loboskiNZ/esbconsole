<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongMood extends Model
{
    protected $table = 'song_moods';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return config('portal.library_connection');
    }

    /**
     * @return HasMany<Song, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'mood_id');
    }
}
