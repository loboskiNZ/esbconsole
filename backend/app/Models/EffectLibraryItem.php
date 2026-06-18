<?php

namespace App\Models;

use App\Enums\EffectImplementationType;
use App\Enums\X32SlotGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EffectLibraryItem extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'x32_algorithm_code',
        'x32_algorithm_id',
        'x32_slot_group',
        'category',
        'implementation_type',
        'max_instances_per_package',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'x32_slot_group' => X32SlotGroup::class,
            'implementation_type' => EffectImplementationType::class,
            'max_instances_per_package' => 'integer',
            'x32_algorithm_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(EffectLibraryParameter::class)->orderBy('parameter_number');
    }

    public function activeParameters(): HasMany
    {
        return $this->parameters()->where('is_active', true);
    }

    public function effectPackageItems(): HasMany
    {
        return $this->hasMany(EffectPackageItem::class);
    }

    public function countsTowardFxSlotLimit(): bool
    {
        return match ($this->implementation_type) {
            EffectImplementationType::FxSlot,
            EffectImplementationType::Hybrid => true,
            EffectImplementationType::ChannelProcessing,
            EffectImplementationType::MainProcessing => false,
        };
    }
}
