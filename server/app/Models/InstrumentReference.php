<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InstrumentReference extends Model
{
    protected $table = 'instrument_reference';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'slug',
        'name',
        'family',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_instruments', 'instrument_id', 'person_id')
            ->withPivot(['role_label', 'is_primary', 'notes'])
            ->withTimestamps();
    }
}
