<?php

namespace App\Models;

use Database\Factories\PersonInstrumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonInstrument extends Model
{
    /** @use HasFactory<PersonInstrumentFactory> */
    use HasFactory;

    protected $fillable = [
        'person_id',
        'instrument_id',
        'role_label',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(InstrumentReference::class, 'instrument_id');
    }
}
