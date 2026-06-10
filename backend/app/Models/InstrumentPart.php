<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\InstrumentPartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentPart extends Model
{
    /** @use HasFactory<InstrumentPartFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(Capability::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function songInstrumentParts(): HasMany
    {
        return $this->hasMany(SongInstrumentPart::class);
    }
}
