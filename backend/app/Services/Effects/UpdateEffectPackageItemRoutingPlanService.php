<?php

namespace App\Services\Effects;

use App\Enums\EffectReturnDestination;
use App\Enums\EffectRoutingMode;
use App\Enums\EffectRoutingTargetSection;
use App\Models\EffectPackageItem;
use Illuminate\Validation\ValidationException;

class UpdateEffectPackageItemRoutingPlanService
{
    public function __construct(
        private readonly EffectPackageItemTargetSectionSync $targetSectionSync,
    ) {}

    /**
     * @param  array{
     *     routing_mode: ?string,
     *     target_sections?: ?list<string>,
     *     return_destination: ?string,
     *     default_return_level: ?string,
     *     notes: ?string
     * } $data
     */
    public function update(EffectPackageItem $item, array $data): EffectPackageItem
    {
        $routingMode = $this->parseEnum($data['routing_mode'] ?? null, EffectRoutingMode::class, 'routing_mode');
        $returnDestination = $this->parseEnum($data['return_destination'] ?? null, EffectReturnDestination::class, 'return_destination');

        $defaultReturnLevel = $data['default_return_level'] ?? null;

        if ($defaultReturnLevel !== null && $defaultReturnLevel !== '') {
            if (! is_numeric($defaultReturnLevel)) {
                throw ValidationException::withMessages([
                    'default_return_level' => 'Default return level must be numeric.',
                ]);
            }

            $defaultReturnLevel = number_format((float) $defaultReturnLevel, 2, '.', '');
        } else {
            $defaultReturnLevel = null;
        }

        $item->update([
            'routing_mode' => $routingMode?->value,
            'return_destination' => $returnDestination?->value,
            'default_return_level' => $defaultReturnLevel,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $item->notes,
        ]);

        if (array_key_exists('target_sections', $data)) {
            $this->targetSectionSync->sync($item, $data['target_sections'] ?? []);
        }

        return $item->fresh(['targetSections']);
    }

    /**
     * @param  class-string<EffectRoutingMode|EffectRoutingTargetSection|EffectReturnDestination>  $enumClass
     */
    private function parseEnum(?string $value, string $enumClass, string $field): EffectRoutingMode|EffectRoutingTargetSection|EffectReturnDestination|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $enum = $enumClass::tryFrom($value);

        if ($enum === null) {
            throw ValidationException::withMessages([
                $field => 'Selected value is not valid.',
            ]);
        }

        return $enum;
    }
}
