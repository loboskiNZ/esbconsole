<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EffectPackageItemParameter extends Model
{
    protected $fillable = [
        'effect_package_item_id',
        'source_effect_parameter_id',
        'source_effect_library_parameter_id',
        'parameter_number',
        'parameter_name',
        'value_type',
        'value',
        'min_value',
        'max_value',
        'unit',
        'enum_values_json',
        'scaling_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'parameter_number' => 'integer',
            'enum_values_json' => 'array',
        ];
    }

    public function effectPackageItem(): BelongsTo
    {
        return $this->belongsTo(EffectPackageItem::class);
    }

    public function sourceEffectParameter(): BelongsTo
    {
        return $this->belongsTo(X32EffectParameter::class, 'source_effect_parameter_id');
    }

    public function sourceLibraryParameter(): BelongsTo
    {
        return $this->belongsTo(EffectLibraryParameter::class, 'source_effect_library_parameter_id');
    }
}
