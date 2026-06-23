<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'people';

    protected $fillable = [
        'band_id',
        'legal_first_name',
        'legal_middle_names',
        'legal_last_name',
        'artistic_name',
        'email',
        'phone',
        'gender',
        'pronouns',
        'city',
        'country',
        'dietary_requirements',
        'notes',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function secureFields(): HasMany
    {
        return $this->hasMany(PersonSecureField::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(PersonFile::class);
    }

    public function personInstruments(): HasMany
    {
        return $this->hasMany(PersonInstrument::class);
    }

    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(InstrumentReference::class, 'person_instruments', 'person_id', 'instrument_id')
            ->withPivot(['role_label', 'is_primary', 'notes'])
            ->withTimestamps();
    }

    public function iemSettings(): HasMany
    {
        return $this->hasMany(PersonIemSetting::class);
    }

    public function displayName(): string
    {
        if (filled($this->artistic_name)) {
            return $this->artistic_name;
        }

        return trim(collect([
            $this->legal_first_name,
            $this->legal_middle_names,
            $this->legal_last_name,
        ])->filter()->implode(' '));
    }
}
