<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSignature extends Model
{
    protected $table = 'time_signatures';

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
        return $this->hasMany(Song::class);
    }
}
