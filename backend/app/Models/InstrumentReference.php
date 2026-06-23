<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\InstrumentReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentReference extends Model
{
    /** @use HasFactory<InstrumentReferenceFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'instrument_reference';

    protected $fillable = [
        'name',
        'family',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function personInstruments(): HasMany
    {
        return $this->hasMany(PersonInstrument::class, 'instrument_id');
    }
}
