<?php

namespace Database\Factories;

use App\Enums\EffectActiveSongSafety;
use App\Enums\EffectImplementationType;
use App\Enums\EffectTempoBehavior;
use App\Enums\X32SlotGroup;
use App\Models\EffectDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EffectDefinition>
 */
class EffectDefinitionFactory extends Factory
{
    protected $model = EffectDefinition::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'category' => EffectDefinition::CATEGORY_DELAY,
            'target_section' => EffectDefinition::TARGET_VOCAL,
            'x32_algorithm_code' => 'DLY',
            'x32_algorithm_id' => 10,
            'x32_slot_group' => X32SlotGroup::Fx1To4,
            'effect_role' => EffectDefinition::ROLE_DELAY,
            'implementation_type' => EffectImplementationType::FxSlot,
            'tempo_behavior' => EffectTempoBehavior::MusicalTimeAware,
            'active_song_safety' => EffectActiveSongSafety::SafeDuringSong,
            'default_parameters_json' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function unknownAlgorithmIdentity(): static
    {
        return $this->state(fn (): array => [
            'x32_algorithm_code' => null,
            'x32_algorithm_id' => null,
            'active_song_safety' => EffectActiveSongSafety::Unknown,
            'notes' => 'Algorithm identity not yet verified on live console.',
        ]);
    }

    public function mainProcessing(): static
    {
        return $this->state(fn (): array => [
            'implementation_type' => EffectImplementationType::MainProcessing,
            'target_section' => EffectDefinition::TARGET_FOH,
            'x32_slot_group' => X32SlotGroup::Fx5To8,
        ]);
    }
}
