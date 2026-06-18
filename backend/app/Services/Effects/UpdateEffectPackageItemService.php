<?php

namespace App\Services\Effects;

use App\Models\EffectPackageItem;
use App\Models\EffectPackageItemParameter;
use Illuminate\Support\Facades\DB;

class UpdateEffectPackageItemService
{
    public function __construct(
        private readonly UpdateEffectPackageItemSlotService $slotService,
        private readonly UpdateEffectPackageItemRoutingPlanService $routingPlanService,
        private readonly UpdateEffectPackageItemParameterService $parameterService,
    ) {}

    /**
     * @param  array{
     *     preferred_slot_number?: ?int,
     *     routing_mode?: ?string,
     *     target_sections?: ?list<string>,
     *     return_destination?: ?string,
     *     default_return_level?: ?string,
     *     notes?: ?string,
     *     parameters?: array<int, ?string>
     * } $data
     */
    public function update(EffectPackageItem $item, array $data): EffectPackageItem
    {
        return DB::transaction(function () use ($item, $data): EffectPackageItem {
            $item->loadMissing(['x32Effect', 'effectDefinition', 'parameters', 'targetSections', 'effectPackage']);

            if (array_key_exists('preferred_slot_number', $data)) {
                $this->slotService->update($item, $data['preferred_slot_number']);
                $item = $item->fresh(['x32Effect', 'effectDefinition', 'parameters', 'targetSections', 'effectPackage']);
            }

            $this->routingPlanService->update($item, [
                'routing_mode' => $data['routing_mode'] ?? $item->routing_mode?->value,
                'target_sections' => $data['target_sections'] ?? $item->selectedTargetSectionValues(),
                'return_destination' => $data['return_destination'] ?? $item->return_destination?->value,
                'default_return_level' => $data['default_return_level'] ?? $item->default_return_level,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $item->notes,
            ]);

            if (! empty($data['parameters']) && is_array($data['parameters'])) {
                foreach ($data['parameters'] as $parameterId => $value) {
                    $parameter = $item->parameters
                        ->firstWhere('id', (int) $parameterId);

                    if (! $parameter instanceof EffectPackageItemParameter) {
                        continue;
                    }

                    $this->parameterService->update($parameter, is_string($value) ? $value : null);
                }
            }

            return $item->fresh([
                'x32Effect',
                'effectDefinition',
                'parameters',
                'targetSections',
                'effectPackage',
            ]);
        });
    }
}
