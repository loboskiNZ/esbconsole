<?php

namespace App\Services\Effects;

use App\Enums\EffectReturnDestination;
use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use App\Models\X32Effect;

class EffectRoutingPlanSuggester
{
    /**
     * @return array{
     *     routing_mode: EffectRoutingMode,
     *     target_sections: list<EffectRoutingTargetSection>,
     *     return_destination: EffectReturnDestination,
     *     default_return_level: ?string
     * }
     */
    public function suggest(?X32Effect $effect): array
    {
        if ($effect === null) {
            return $this->defaults();
        }

        $operatorName = strtolower($effect->operator_name ?? $effect->effect_name ?? '');

        if ($this->suggestsMainProcessing($operatorName)) {
            return [
                'routing_mode' => EffectRoutingMode::MainProcessing,
                'target_sections' => [EffectRoutingTargetSection::Foh],
                'return_destination' => EffectReturnDestination::MainLr,
                'default_return_level' => '0.00',
            ];
        }

        $category = strtolower($effect->operator_category ?? '');

        if (in_array($category, ['reverb', 'delay', 'modulation'], true)) {
            return [
                'routing_mode' => EffectRoutingMode::SendReturn,
                'target_sections' => [],
                'return_destination' => EffectReturnDestination::MainLr,
                'default_return_level' => '-10.00',
            ];
        }

        return $this->defaults();
    }

    /**
     * @return array{
     *     routing_mode: EffectRoutingMode,
     *     target_sections: list<EffectRoutingTargetSection>,
     *     return_destination: EffectReturnDestination,
     *     default_return_level: ?string
     * }
     */
    private function defaults(): array
    {
        return [
            'routing_mode' => EffectRoutingMode::NotConfigured,
            'target_sections' => [],
            'return_destination' => EffectReturnDestination::NotConfigured,
            'default_return_level' => null,
        ];
    }

    private function suggestsMainProcessing(string $operatorName): bool
    {
        foreach (['graphic eq', 'true eq', 'limiter', 'compressor'] as $needle) {
            if (str_contains($operatorName, $needle)) {
                return true;
            }
        }

        return false;
    }
}
