<?php

namespace App\Models;

use Database\Factories\ShowPlaylistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowPlaylistItem extends Model
{
    /** @use HasFactory<ShowPlaylistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'show_id',
        'song_id',
        'position',
        'ableton_pgm',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
