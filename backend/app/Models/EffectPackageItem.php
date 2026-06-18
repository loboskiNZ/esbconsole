<?php

namespace App\Models;

use App\Enums\EffectReturnDestination;
use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EffectPackageItem extends Model
{
    protected $fillable = [
        'effect_package_id',
        'effect_definition_id',
        'effect_id',
        'effect_library_item_id',
        'is_required',
        'preferred_slot_number',
        'slot_group_preference',
        'routing_mode',
        'target_section',
        'return_destination',
        'default_return_level',
        'priority',
        'parameter_overrides_json',
        'timing_rules_json',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'preferred_slot_number' => 'integer',
            'priority' => 'integer',
            'routing_mode' => EffectRoutingMode::class,
            'target_section' => EffectRoutingTargetSection::class,
            'return_destination' => EffectReturnDestination::class,
            'default_return_level' => 'decimal:2',
            'parameter_overrides_json' => 'array',
            'timing_rules_json' => 'array',
        ];
    }

    public function formattedDefaultReturnLevel(): ?string
    {
        if ($this->default_return_level === null) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format((float) $this->default_return_level, 2, '.', ''), '0'), '.');

        return $formatted.' dB';
    }

    public function routingModeLabel(): string
    {
        return $this->routing_mode?->label() ?? EffectRoutingMode::NotConfigured->label();
    }

    public function routingTargetSectionLabel(): string
    {
        return $this->routingTargetSectionsSummary();
    }

    /**
     * @return list<string>
     */
    public function selectedTargetSectionValues(): array
    {
        return $this->targetSections
            ->sortBy(fn (EffectPackageItemTargetSection $row) => $row->target_section->orderIndex())
            ->pluck('target_section')
            ->map(fn (EffectRoutingTargetSection $section) => $section->value)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function resolvedTargetSectionValues(): array
    {
        if ($this->targetSections->isNotEmpty()) {
            return $this->selectedTargetSectionValues();
        }

        if ($this->target_section !== null && $this->target_section !== EffectRoutingTargetSection::NotConfigured) {
            return [$this->target_section->value];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function selectedTargetSectionLabels(): array
    {
        return $this->targetSections
            ->sortBy(fn (EffectPackageItemTargetSection $row) => $row->target_section->orderIndex())
            ->pluck('target_section')
            ->map(fn (EffectRoutingTargetSection $section) => $section->label())
            ->values()
            ->all();
    }

    public function routingTargetSectionsSummary(): string
    {
        $labels = $this->resolvedTargetSectionLabels();

        return $labels === [] ? 'Not selected' : implode(', ', $labels);
    }

    /**
     * @return list<string>
     */
    public function resolvedTargetSectionLabels(): array
    {
        if ($this->targetSections->isNotEmpty()) {
            return $this->selectedTargetSectionLabels();
        }

        if ($this->target_section !== null && $this->target_section !== EffectRoutingTargetSection::NotConfigured) {
            return [$this->target_section->label()];
        }

        return [];
    }

    public function hasSelectedTargetSections(): bool
    {
        return $this->targetSections->isNotEmpty()
            || ($this->target_section !== null && $this->target_section !== EffectRoutingTargetSection::NotConfigured);
    }

    public function returnDestinationLabel(): string
    {
        return $this->return_destination?->label() ?? EffectReturnDestination::NotConfigured->label();
    }

    public function effectPackage(): BelongsTo
    {
        return $this->belongsTo(EffectPackage::class);
    }

    public function effectDefinition(): BelongsTo
    {
        return $this->belongsTo(EffectDefinition::class);
    }

    public function x32Effect(): BelongsTo
    {
        return $this->belongsTo(X32Effect::class, 'effect_id');
    }

    public function effectLibraryItem(): BelongsTo
    {
        return $this->belongsTo(EffectLibraryItem::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(EffectPackageItemParameter::class)->orderBy('parameter_number');
    }

    public function targetSections(): HasMany
    {
        return $this->hasMany(EffectPackageItemTargetSection::class)->orderBy('target_section');
    }
}
