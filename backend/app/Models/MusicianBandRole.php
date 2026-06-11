<?php

namespace App\Models;

use App\Enums\BandRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicianBandRole extends Model
{
    protected $fillable = [
        'musician_id',
        'role',
    ];

    public function musician(): BelongsTo
    {
        return $this->belongsTo(Musician::class);
    }

    public function bandRole(): ?BandRole
    {
        return BandRole::tryFrom($this->role);
    }
}
