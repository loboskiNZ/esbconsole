<?php

namespace App\Models;

use Database\Factories\PersonIemSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonIemSetting extends Model
{
    /** @use HasFactory<PersonIemSettingFactory> */
    use HasFactory;

    protected $table = 'person_iem_settings';

    protected $fillable = [
        'person_id',
        'name',
        'vocal_level',
        'own_instrument_level',
        'band_level',
        'click_level',
        'tracks_level',
        'reverb_level',
        'ambient_level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'vocal_level' => 'decimal:2',
            'own_instrument_level' => 'decimal:2',
            'band_level' => 'decimal:2',
            'click_level' => 'decimal:2',
            'tracks_level' => 'decimal:2',
            'reverb_level' => 'decimal:2',
            'ambient_level' => 'decimal:2',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
