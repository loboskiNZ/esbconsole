<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\BandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Band extends Model
{
    /** @use HasFactory<BandFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'name',
    ];

    public function shows(): HasMany
    {
        return $this->hasMany(Show::class);
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public function musicians(): HasMany
    {
        return $this->hasMany(Musician::class);
    }

    public function instrumentParts(): HasMany
    {
        return $this->hasMany(InstrumentPart::class);
    }
}
