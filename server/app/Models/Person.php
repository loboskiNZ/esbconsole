<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
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
        'bio',
        'profile_photo_path',
        'dietary_requirements',
        'notes',
    ];

    /**
     * @return BelongsTo<Band, $this>
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return BelongsToMany<InstrumentReference, $this>
     */
    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(InstrumentReference::class, 'person_instruments', 'person_id', 'instrument_id')
            ->withPivot(['role_label', 'is_primary', 'notes'])
            ->withTimestamps();
    }

    public function legalName(): string
    {
        return trim(implode(' ', array_filter([
            $this->legal_first_name,
            $this->legal_middle_names,
            $this->legal_last_name,
        ])));
    }

    public function primaryInstrument(): ?InstrumentReference
    {
        return $this->instruments->first(
            fn ($instrument) => (bool) $instrument->pivot->is_primary,
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, InstrumentReference>
     */
    public function additionalInstruments()
    {
        return $this->instruments->filter(fn ($instrument) => ! $instrument->pivot->is_primary)->values();
    }

    public function hasProfilePhoto(): bool
    {
        return filled($this->profile_photo_path);
    }

    public function instrumentSummary(): string
    {
        $names = $this->instruments
            ->sortByDesc(fn ($instrument) => (bool) $instrument->pivot->is_primary)
            ->pluck('name')
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return '';
        }

        return $names->join(' · ');
    }
}
