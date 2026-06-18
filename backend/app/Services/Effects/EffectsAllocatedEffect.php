<?php

namespace App\Services\Effects;

use App\Enums\EffectActiveSongSafety;
use App\Enums\EffectImplementationType;
use App\Enums\EffectTempoBehavior;
use App\Enums\X32SlotGroup;

readonly class EffectsAllocatedEffect
{
    /**
     * @param  list<string>  $packageSources
     * @param  array<string, mixed>|null  $parameterOverrides
     * @param  array<string, mixed>|null  $timingRules
     */
    public function __construct(
        public int $effectDefinitionId,
        public string $effectName,
        public string $effectSlug,
        public array $packageSources,
        public ?int $slotNumber,
        public X32SlotGroup $slotGroup,
        public ?string $x32AlgorithmCode,
        public ?int $x32AlgorithmId,
        public EffectImplementationType $implementationType,
        public EffectTempoBehavior $tempoBehavior,
        public EffectActiveSongSafety $safety,
        public ?array $parameterOverrides,
        public ?array $timingRules,
        public bool $consumesFxSlot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'effect_definition_id' => $this->effectDefinitionId,
            'effect_name' => $this->effectName,
            'effect_slug' => $this->effectSlug,
            'package_sources' => $this->packageSources,
            'slot_number' => $this->slotNumber,
            'slot_group' => $this->slotGroup->value,
            'x32_algorithm_code' => $this->x32AlgorithmCode,
            'x32_algorithm_id' => $this->x32AlgorithmId,
            'implementation_type' => $this->implementationType->value,
            'tempo_behavior' => $this->tempoBehavior->value,
            'safety' => $this->safety->value,
            'parameter_overrides' => $this->parameterOverrides,
            'timing_rules' => $this->timingRules,
            'consumes_fx_slot' => $this->consumesFxSlot,
        ];
    }
}
