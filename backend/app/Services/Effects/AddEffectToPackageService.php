<?php

namespace App\Services\Effects;

use App\Enums\EffectActiveSongSafety;
use App\Enums\EffectTempoBehavior;
use App\Models\EffectDefinition;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\EffectPackageItemParameter;
use App\Models\X32Effect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddEffectToPackageService
{
    public function __construct(
        private readonly EffectRoutingPlanSuggester $routingPlanSuggester,
        private readonly EffectPackageItemTargetSectionSync $targetSectionSync,
        private readonly EffectPackageItemSlotAvailabilityService $slotAvailability,
        private readonly UpdateEffectPackageItemSlotService $slotService,
    ) {}

    /**
     * @param  array{
     *     effect_id: int,
     *     preferred_slot_number?: ?int
     * } $data
     */
    public function add(EffectPackage $package, array $data): EffectPackageItem
    {
        $package->loadMissing(['effectPackageItems.x32Effect', 'effectPackageTypeOption']);

        $effect = X32Effect::query()
            ->where('is_active', true)
            ->with('activeParameters')
            ->find($data['effect_id']);

        if ($effect === null) {
            throw ValidationException::withMessages([
                'effect_id' => 'Selected effect is not valid or inactive.',
            ]);
        }

        $this->assertEffectNotAlreadyInPackage($package, $effect);
        $this->assertFxSlotLimit($package, $effect);

        $preferredSlot = array_key_exists('preferred_slot_number', $data)
            ? $data['preferred_slot_number']
            : null;

        if ($preferredSlot !== null) {
            $this->assertPreferredSlotAllowed($package, $effect, $preferredSlot);
        }

        return DB::transaction(function () use ($package, $effect, $preferredSlot): EffectPackageItem {
            $definition = $this->resolveOrCreateEffectDefinition($effect);
            $routingPlan = $this->routingPlanSuggester->suggest($effect);
            $nextPriority = ((int) $package->effectPackageItems->max('priority')) + 10;

            $packageItem = EffectPackageItem::query()->create([
                'effect_package_id' => $package->id,
                'effect_definition_id' => $definition->id,
                'effect_id' => $effect->id,
                'is_required' => true,
                'preferred_slot_number' => null,
                'slot_group_preference' => $effect->x32_slot_group->value,
                'routing_mode' => $routingPlan['routing_mode'],
                'return_destination' => $routingPlan['return_destination'],
                'default_return_level' => $routingPlan['default_return_level'],
                'priority' => $nextPriority > 0 ? $nextPriority : 10,
            ]);

            $this->targetSectionSync->syncSuggested($packageItem, $routingPlan['target_sections']);

            foreach ($effect->activeParameters as $parameter) {
                EffectPackageItemParameter::query()->create([
                    'effect_package_item_id' => $packageItem->id,
                    'source_effect_parameter_id' => $parameter->id,
                    'parameter_number' => $parameter->parameter_number,
                    'parameter_name' => $parameter->parameter_name,
                    'value_type' => $parameter->value_type,
                    'value' => $parameter->default_value,
                    'min_value' => $parameter->min_value,
                    'max_value' => $parameter->max_value,
                    'unit' => $parameter->unit,
                    'enum_values_json' => $parameter->enum_values_json,
                    'scaling_notes' => $parameter->scaling_notes,
                ]);
            }

            if ($preferredSlot !== null) {
                $this->slotService->update($packageItem->fresh(['x32Effect', 'effectDefinition', 'effectPackage']), $preferredSlot);
            }

            return $packageItem->fresh([
                'x32Effect',
                'effectDefinition',
                'parameters',
                'targetSections',
            ]);
        });
    }

    private function assertEffectNotAlreadyInPackage(EffectPackage $package, X32Effect $effect): void
    {
        $exists = $package->effectPackageItems
            ->contains(fn (EffectPackageItem $item) => $item->effect_id === $effect->id);

        if ($exists) {
            throw ValidationException::withMessages([
                'effect_id' => 'This effect is already in this package.',
            ]);
        }
    }

    private function assertFxSlotLimit(EffectPackage $package, X32Effect $effect): void
    {
        if (! $effect->countsTowardFxSlotLimit()) {
            return;
        }

        $existingCount = $package->effectPackageItems
            ->filter(fn (EffectPackageItem $item) => $item->x32Effect?->countsTowardFxSlotLimit() ?? false)
            ->count();

        if ($existingCount >= CreateEffectPackageService::MAX_FX_SLOT_EFFECTS) {
            throw ValidationException::withMessages([
                'effect_id' => sprintf(
                    'Package cannot contain more than %d FX-slot-consuming effects.',
                    CreateEffectPackageService::MAX_FX_SLOT_EFFECTS,
                ),
            ]);
        }
    }

    private function assertPreferredSlotAllowed(EffectPackage $package, X32Effect $effect, int $preferredSlot): void
    {
        if (! in_array($preferredSlot, $effect->x32_slot_group->allowedSlotNumbers(), true)) {
            throw ValidationException::withMessages([
                'preferred_slot_number' => sprintf(
                    '%s can only use %s.',
                    $effect->displayName(),
                    $effect->x32_slot_group->allowedSlotsHelper(),
                ),
            ]);
        }

        $reason = $this->slotAvailability->reasonForNewItemSlot($package, $preferredSlot);

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'preferred_slot_number' => $reason['message'],
            ]);
        }
    }

    private function resolveOrCreateEffectDefinition(X32Effect $effect): EffectDefinition
    {
        $slug = Str::slug(strtolower($effect->effect_code.'-'.$effect->x32_slot_group->value));

        $existing = EffectDefinition::query()
            ->where('x32_algorithm_code', $effect->effect_code)
            ->where('x32_slot_group', $effect->x32_slot_group)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return EffectDefinition::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $effect->effect_name,
                'category' => $effect->category,
                'target_section' => $this->mapTargetSection($effect->category),
                'x32_algorithm_code' => $effect->effect_code,
                'x32_algorithm_id' => $effect->x32_algorithm_id,
                'x32_slot_group' => $effect->x32_slot_group,
                'effect_role' => EffectDefinition::ROLE_PROCESSOR,
                'implementation_type' => $effect->implementation_type,
                'tempo_behavior' => EffectTempoBehavior::TempoNeutral,
                'active_song_safety' => EffectActiveSongSafety::BetweenSongOnly,
                'is_active' => true,
            ],
        );
    }

    private function mapTargetSection(string $category): string
    {
        return match ($category) {
            'graphic_eq', 'limiter', 'compressor' => EffectDefinition::TARGET_FOH,
            'horn', 'reverb', 'room' => EffectDefinition::TARGET_HORN,
            'plate', 'delay', 'enhancer', 'hall' => EffectDefinition::TARGET_VOCAL,
            default => EffectDefinition::TARGET_SPECIAL,
        };
    }
}
