<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongMood extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'colour_hex',
        'accent_colour_hex',
        'description',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Song, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'mood_id');
    }
}
