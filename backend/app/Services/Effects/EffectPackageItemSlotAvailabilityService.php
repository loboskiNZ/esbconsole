<?php

namespace App\Services\Effects;

use App\Enums\EffectPackageType;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EffectPackageItemSlotAvailabilityService
{
    /**
     * @return array{
     *     reason: 'same_package'|'permanent_reservation',
     *     message: string
     * }|null
     */
    public function reasonForSlot(EffectPackageItem $item, int $slotNumber): ?array
    {
        if ($item->preferred_slot_number === $slotNumber) {
            return null;
        }

        $samePackageConflict = $this->findSamePackageConflict($item, $slotNumber);

        if ($samePackageConflict !== null) {
            return [
                'reason' => 'same_package',
                'message' => sprintf(
                    'FX%d is already used by %s in this package.',
                    $slotNumber,
                    $this->displayName($samePackageConflict),
                ),
            ];
        }

        $permanentConflict = $this->findPermanentReservationConflict($item, $slotNumber);

        if ($permanentConflict !== null) {
            return [
                'reason' => 'permanent_reservation',
                'message' => sprintf(
                    'FX%d is reserved by permanent package %s.',
                    $slotNumber,
                    strtoupper($permanentConflict->effectPackage->name),
                ),
            ];
        }

        return null;
    }

    public function assertSlotAvailable(EffectPackageItem $item, ?int $slotNumber): void
    {
        if ($slotNumber === null) {
            return;
        }

        $reason = $this->reasonForSlot($item, $slotNumber);

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'preferred_slot_number' => $reason['message'],
            ]);
        }
    }

    /**
     * @return array{
     *     reason: 'same_package'|'permanent_reservation',
     *     message: string
     * }|null
     */
    public function reasonForNewItemSlot(EffectPackage $package, int $slotNumber): ?array
    {
        $package->loadMissing('effectPackageItems.x32Effect', 'effectPackageItems.effectDefinition');

        $samePackageConflict = $package->effectPackageItems
            ->first(fn (EffectPackageItem $other) => $other->preferred_slot_number === $slotNumber);

        if ($samePackageConflict !== null) {
            return [
                'reason' => 'same_package',
                'message' => sprintf(
                    'FX%d is already used by %s in this package.',
                    $slotNumber,
                    $this->displayName($samePackageConflict),
                ),
            ];
        }

        $permanentConflict = $this->findPermanentReservationForPackage($package, $slotNumber);

        if ($permanentConflict !== null) {
            return [
                'reason' => 'permanent_reservation',
                'message' => sprintf(
                    'FX%d is reserved by permanent package %s.',
                    $slotNumber,
                    strtoupper($permanentConflict->effectPackage->name),
                ),
            ];
        }

        return null;
    }

    private function findSamePackageConflict(EffectPackageItem $item, int $slotNumber): ?EffectPackageItem
    {
        $item->loadMissing('effectPackage.effectPackageItems.x32Effect', 'effectPackage.effectPackageItems.effectDefinition');

        return $item->effectPackage->effectPackageItems
            ->first(fn (EffectPackageItem $other) => $other->id !== $item->id
                && $other->preferred_slot_number === $slotNumber);
    }

    private function findPermanentReservationConflict(EffectPackageItem $item, int $slotNumber): ?EffectPackageItem
    {
        $item->loadMissing('effectPackage');

        return $this->findPermanentReservationForPackage($item->effectPackage, $slotNumber);
    }

    private function findPermanentReservationForPackage(EffectPackage $package, int $slotNumber): ?EffectPackageItem
    {
        $packageType = $package->package_type;

        if ($packageType === EffectPackageType::Permanent) {
            return EffectPackageItem::query()
                ->where('preferred_slot_number', $slotNumber)
                ->whereHas('effectPackage', fn ($query) => $query
                    ->where('is_active', true)
                    ->where('package_type', EffectPackageType::Permanent)
                    ->where('id', '!=', $package->id))
                ->with('effectPackage')
                ->first();
        }

        if (! $packageType->mayReuseSlotsAcrossPackages()) {
            return null;
        }

        return EffectPackageItem::query()
            ->where('preferred_slot_number', $slotNumber)
            ->whereHas('effectPackage', fn ($query) => $query
                ->where('is_active', true)
                ->where('package_type', EffectPackageType::Permanent))
            ->with('effectPackage')
            ->first();
    }

    /**
     * @return Collection<int, array{reason: string, message: string}>
     */
    public function unavailableSlotsForItem(EffectPackageItem $item): Collection
    {
        return collect(range(1, 8))
            ->mapWithKeys(function (int $slot) use ($item) {
                $reason = $this->reasonForSlot($item, $slot);

                return $reason === null ? [] : [$slot => $reason];
            });
    }

    private function displayName(EffectPackageItem $item): string
    {
        return $item->x32Effect?->displayName()
            ?? $item->effectDefinition?->name
            ?? 'Effect';
    }
}
