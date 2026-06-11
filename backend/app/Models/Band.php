<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\BandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Band extends Model
{
    /** @use HasFactory<BandFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'name',
        'primary_director_musician_id',
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

    public function primaryDirector(): BelongsTo
    {
        return $this->belongsTo(Musician::class, 'primary_director_musician_id');
    }

    public function instrumentParts(): HasMany
    {
        return $this->hasMany(InstrumentPart::class);
    }

    public function abletonShowFiles(): HasMany
    {
        return $this->hasMany(AbletonShowFile::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    public function actionDefinitions(): HasMany
    {
        return $this->hasMany(ActionDefinition::class);
    }

    public function integrationDevices(): HasMany
    {
        return $this->hasMany(IntegrationDevice::class);
    }
}
