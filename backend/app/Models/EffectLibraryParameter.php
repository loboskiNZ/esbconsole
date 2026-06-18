<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EffectLibraryParameter extends Model
{
    protected $fillable = [
        'effect_library_item_id',
        'parameter_number',
        'parameter_name',
        'value_type',
        'default_value',
        'min_value',
        'max_value',
        'unit',
        'enum_values_json',
        'scaling_notes',
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

    public function effectLibraryItem(): BelongsTo
    {
        return $this->belongsTo(EffectLibraryItem::class);
    }

    public function packageItemParameters(): HasMany
    {
        return $this->hasMany(EffectPackageItemParameter::class, 'source_effect_library_parameter_id');
    }
}
