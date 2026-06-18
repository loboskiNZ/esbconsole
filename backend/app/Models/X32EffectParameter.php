<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class X32EffectParameter extends Model
{
    protected $table = 'effect_parameters';

    protected $fillable = [
        'effect_id',
        'parameter_number',
        'parameter_name',
        'value_type',
        'min_value',
        'max_value',
        'unit',
        'enum_values_json',
        'scaling_notes',
        'default_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parameter_number' => 'integer',
            'enum_values_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function effect(): BelongsTo
    {
        return $this->belongsTo(X32Effect::class, 'effect_id');
    }

    public function packageItemParameters(): HasMany
    {
        return $this->hasMany(EffectPackageItemParameter::class, 'source_effect_parameter_id');
    }
}
