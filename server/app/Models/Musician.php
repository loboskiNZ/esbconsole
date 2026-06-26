<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Musician extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'band_id',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'notes',
        'active',
        'user_id',
    ];

    /**
     * @return HasMany<PerformanceAssignment, $this>
     */
    public function performanceAssignments(): HasMany
    {
        return $this->hasMany(PerformanceAssignment::class);
    }

    public function displayLabel(): string
    {
        if (is_string($this->display_name) && trim($this->display_name) !== '') {
            return trim($this->display_name);
        }

        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));
    }
}
