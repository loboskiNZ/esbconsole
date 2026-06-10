<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'name',
        'lifecycle_state',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(ShowPlaylistItem::class)->orderBy('position');
    }
}
