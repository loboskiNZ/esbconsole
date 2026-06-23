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
}
