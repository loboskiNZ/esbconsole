<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\CueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cue extends Model
{
    /** @use HasFactory<CueFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'song_id',
        'cue_number',
        'name',
        'description',
        'notes',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function snippets(): HasMany
    {
        return $this->hasMany(Snippet::class);
    }

    public function performanceAssignments(): HasMany
    {
        return $this->hasMany(PerformanceAssignment::class);
    }

    public function cueActions(): HasMany
    {
        return $this->hasMany(CueAction::class)->orderBy('sort_order')->orderBy('id');
    }

    public function runtimeIdentity(): string
    {
        return $this->song->song_code.'.'.$this->cue_number;
    }
}
