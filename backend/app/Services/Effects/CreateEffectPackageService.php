<?php

namespace App\Services\Effects;

use App\Enums\EffectActiveSongSafety;
use App\Enums\EffectImplementationType;
use App\Enums\EffectTempoBehavior;
use App\Models\EffectDefinition;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\EffectPackageItemParameter;
use App\Models\EffectPackageTypeOption;
use App\Models\X32Effect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateEffectPackageService
{
    public function __construct(
        private readonly EffectRoutingPlanSuggester $routingPlanSuggester,
        private readonly EffectPackageItemTargetSectionSync $targetSectionSync,
    ) {}

    public const MAX_FX_SLOT_EFFECTS = 8;

    /**
     * @param  array{
     *     name: string,
     *     description: ?string,
     *     effect_package_type_id: int,
     *     effect_ids: array<int, int>
     * } $data
     */
    public function create(array $data): EffectPackage
    {
        $packageType = EffectPackageTypeOption::query()
            ->where('is_active', true)
            ->findOrFail($data['effect_package_type_id']);

        $effects = X32Effect::query()
            ->where('is_active', true)
            ->whereIn('id', $data['effect_ids'])
            ->get()
            ->keyBy('id');

        if ($effects->count() !== count(array_unique($data['effect_ids']))) {
            throw ValidationException::withMessages([
                'effect_ids' => 'One or more selected effects are invalid or inactive.',
            ]);
        }

        $orderedEffects = collect($data['effect_ids'])
            ->map(fn (int $id) => $effects->get($id))
            ->filter()
            ->values();

        $this->assertFxSlotLimit($orderedEffects);

        $name = Str::upper(trim($data['name']));
        $slug = $this->uniqueSlug($name);
        $targetSection = $this->resolveTargetSection($orderedEffects);

        return DB::transaction(function () use ($data, $packageType, $orderedEffects, $name, $slug, $targetSection): EffectPackage {
            $package = EffectPackage::query()->create([
                'name' => $name,
                'slug' => $slug,
                'effect_package_type_id' => $packageType->id,
                'package_type' => $packageType->toPackageTypeEnum(),
                'target_section' => $targetSection,
                'priority' => 100,
                'description' => $data['description'] ?? null,
                'is_default' => false,
                'is_active' => true,
            ]);

            foreach ($orderedEffects as $index => $effect) {
                $definition = $this->resolveOrCreateEffectDefinition($effect);
                $routingPlan = $this->routingPlanSuggester->suggest($effect);

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
                    'priority' => ($index + 1) * 10,
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
            }

            return $package->fresh([
                'effectPackageTypeOption',
                'effectPackageItems.x32Effect',
                'effectPackageItems.effectDefinition',
                'effectPackageItems.parameters',
                'effectPackageItems.targetSections',
            ]);
        });
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

    /**
     * @param  \Illuminate\Support\Collection<int, X32Effect>  $effects
     */
    private function assertFxSlotLimit($effects): void
    {
        $slotCount = $effects
            ->filter(fn (X32Effect $effect) => $effect->countsTowardFxSlotLimit())
            ->count();

        if ($slotCount > self::MAX_FX_SLOT_EFFECTS) {
            throw ValidationException::withMessages([
                'effect_ids' => sprintf(
                    'Package cannot contain more than %d FX-slot-consuming effects (selected %d).',
                    self::MAX_FX_SLOT_EFFECTS,
                    $slotCount,
                ),
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, X32Effect>  $effects
     */
    private function resolveTargetSection($effects): string
    {
        $first = $effects->first();

        if ($first === null) {
            return EffectPackage::TARGET_SPECIAL;
        }

        return match ($first->category) {
            'graphic_eq', 'limiter', 'compressor' => EffectPackage::TARGET_FOH,
            'horn', 'reverb', 'room' => EffectPackage::TARGET_HORN,
            'plate', 'delay', 'enhancer', 'hall' => EffectPackage::TARGET_VOCAL,
            default => EffectPackage::TARGET_SPECIAL,
        };
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug(Str::lower($name));
        $base = $base !== '' ? $base : 'effect-package';
        $slug = $base;
        $suffix = 2;

        while (EffectPackage::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
