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
        'ableton_show_file_id',
        'name',
        'description',
        'lifecycle_state',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function abletonShowFile(): BelongsTo
    {
        return $this->belongsTo(AbletonShowFile::class);
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(ShowPlaylistItem::class)->orderBy('position');
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    public function consoleLearningSnapshots(): HasMany
    {
        return $this->hasMany(ConsoleLearningSnapshot::class);
    }

    public function consoleBaselines(): HasMany
    {
        return $this->hasMany(ShowConsoleBaseline::class);
    }
}
