<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSignature extends Model
{
    protected $fillable = [
        'label',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Song, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }
}
