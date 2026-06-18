<?php

namespace App\Models;

use App\Enums\EffectImplementationType;
use App\Enums\X32SlotGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class X32Effect extends Model
{
    protected $table = 'effects';

    protected $fillable = [
        'effect_code',
        'effect_name',
        'operator_name',
        'operator_description',
        'recommended_for_json',
        'operator_category',
        'difficulty',
        'starter_notes',
        'x32_algorithm_id',
        'x32_slot_group',
        'category',
        'implementation_type',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'x32_slot_group' => X32SlotGroup::class,
            'implementation_type' => EffectImplementationType::class,
            'recommended_for_json' => 'array',
            'x32_algorithm_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(X32EffectParameter::class, 'effect_id')->orderBy('parameter_number');
    }

    public function activeParameters(): HasMany
    {
        return $this->parameters()->where('is_active', true);
    }

    public function effectPackageItems(): HasMany
    {
        return $this->hasMany(EffectPackageItem::class, 'effect_id');
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

    public function slotGroupLabel(): string
    {
        return $this->x32_slot_group->label();
    }

    public function displayName(): string
    {
        return $this->operator_name ?: $this->effect_name;
    }

    public function selectorPrimaryLabel(): string
    {
        return sprintf(
            '%s — %s — %s',
            $this->displayName(),
            $this->effect_code,
            $this->slotGroupLabel(),
        );
    }

    public function selectorSecondaryLabel(): string
    {
        return sprintf(
            'X32: %s · %s · Algorithm %s · %s',
            $this->effect_name,
            $this->effect_code,
            $this->x32_algorithm_id,
            $this->slotGroupLabel(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function recommendedForTargets(): array
    {
        return $this->recommended_for_json ?? [];
    }
}
