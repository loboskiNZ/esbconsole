<?php

namespace App\Services\Effects;

use App\Enums\X32SlotGroup;
use App\Models\EffectPackageItem;
use Illuminate\Validation\ValidationException;

class UpdateEffectPackageItemSlotService
{
    public function __construct(
        private readonly EffectPackageItemSlotAvailabilityService $slotAvailability,
    ) {}

    public function update(EffectPackageItem $item, ?int $preferredSlotNumber): EffectPackageItem
    {
        $slotGroup = $this->resolveSlotGroup($item);
        $displayName = $this->displayName($item);

        if ($preferredSlotNumber !== null) {
            if ($preferredSlotNumber < 1 || $preferredSlotNumber > 8) {
                throw ValidationException::withMessages([
                    'preferred_slot_number' => 'Slot must be between FX1 and FX8.',
                ]);
            }

            if (! in_array($preferredSlotNumber, $slotGroup->allowedSlotNumbers(), true)) {
                throw ValidationException::withMessages([
                    'preferred_slot_number' => sprintf(
                        '%s can only use %s.',
                        $displayName,
                        $slotGroup->allowedSlotsHelper(),
                    ),
                ]);
            }

            $item->loadMissing(['effectPackage', 'x32Effect', 'effectDefinition']);
            $this->slotAvailability->assertSlotAvailable($item, $preferredSlotNumber);
        }

        $item->update(['preferred_slot_number' => $preferredSlotNumber]);

        return $item->fresh();
    }

    private function resolveSlotGroup(EffectPackageItem $item): X32SlotGroup
    {
        if ($item->x32Effect?->x32_slot_group instanceof X32SlotGroup) {
            return $item->x32Effect->x32_slot_group;
        }

        if ($item->effectDefinition?->x32_slot_group instanceof X32SlotGroup) {
            return $item->effectDefinition->x32_slot_group;
        }

        return X32SlotGroup::Any;
    }

    private function displayName(EffectPackageItem $item): string
    {
        return $item->x32Effect?->displayName()
            ?? $item->effectDefinition?->name
            ?? 'Effect';
    }
}
