<?php

namespace App\Services\Effects;

use App\Enums\EffectPackageDeploymentPlanStatus;
use App\Enums\EffectPackageType;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use Illuminate\Support\Collection;

class EffectPackageDeploymentPlanPreviewService
{
    public function __construct(
        private readonly EffectPackageItemSlotAvailabilityService $slotAvailability,
    ) {}

    /**
     * @return array{
     *     package_name: string,
     *     package_type_label: string,
     *     fx_slots_used: int,
     *     status: EffectPackageDeploymentPlanStatus,
     *     status_label: string,
     *     slot_rows: list<array<string, mixed>>,
     *     unallocated_effects: list<array<string, mixed>>,
     *     conflicts: list<string>,
     *     permanent_reservations: list<array<string, mixed>>,
     *     warnings: list<string>
     * }|null
     */
    public function preview(?EffectPackage $package): ?array
    {
        if ($package === null) {
            return null;
        }

        $package->loadMissing([
            'effectPackageTypeOption',
            'effectPackageItems.x32Effect',
            'effectPackageItems.effectDefinition',
        ]);

        $conflicts = [];
        $warnings = [];
        $slotRows = [];
        $itemsBySlot = $package->effectPackageItems
            ->filter(fn (EffectPackageItem $item) => $item->preferred_slot_number !== null)
            ->groupBy('preferred_slot_number');

        foreach (range(1, 8) as $slotNumber) {
            $slotRows[] = $this->buildSlotRow($package, $slotNumber, $itemsBySlot->get($slotNumber), $conflicts);
        }

        $unallocatedEffects = $package->effectPackageItems
            ->filter(fn (EffectPackageItem $item) => $item->preferred_slot_number === null)
            ->map(fn (EffectPackageItem $item) => $this->effectSummary($item, $package->name))
            ->values()
            ->all();

        if ($unallocatedEffects !== []) {
            $warnings[] = sprintf(
                '%d effect%s not allocated to an FX slot.',
                count($unallocatedEffects),
                count($unallocatedEffects) === 1 ? '' : 's',
            );
        }

        $permanentReservations = $this->permanentReservationsForPackage($package);

        $status = $this->determineStatus($conflicts, $warnings);

        return [
            'package_name' => $package->name,
            'package_type_label' => $package->effectPackageTypeOption?->name ?? $package->package_type->name,
            'fx_slots_used' => $this->countFxSlotsUsed($package),
            'status' => $status,
            'status_label' => $status->label(),
            'slot_rows' => $slotRows,
            'unallocated_effects' => $unallocatedEffects,
            'conflicts' => array_values(array_unique($conflicts)),
            'permanent_reservations' => $permanentReservations,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  Collection<int, EffectPackageItem>|null  $itemsAtSlot
     * @param  list<string>  $conflicts
     * @return array<string, mixed>
     */
    private function buildSlotRow(
        EffectPackage $package,
        int $slotNumber,
        ?Collection $itemsAtSlot,
        array &$conflicts,
    ): array {
        $base = [
            'slot' => $slotNumber,
            'slot_label' => 'FX'.$slotNumber,
            'effect_name' => '—',
            'effect_code' => '—',
            'algorithm_id' => '—',
            'package_source' => '—',
            'status' => 'Available',
            'status_class' => 'available',
        ];

        if ($itemsAtSlot === null || $itemsAtSlot->isEmpty()) {
            $reservation = $this->findPermanentReservationForSlot($package, $slotNumber);

            if ($reservation !== null) {
                $base['package_source'] = strtoupper($reservation['package_name']);
                $base['effect_name'] = $reservation['effect_name'];
                $base['effect_code'] = $reservation['effect_code'];
                $base['algorithm_id'] = $reservation['algorithm_id'];
                $base['status'] = 'Reserved';
                $base['status_class'] = 'reserved';
            }

            return $base;
        }

        if ($itemsAtSlot->count() > 1) {
            $names = $itemsAtSlot
                ->map(fn (EffectPackageItem $item) => $this->displayName($item))
                ->implode(', ');

            $message = sprintf('FX%d is allocated to multiple effects in this package (%s).', $slotNumber, $names);
            $conflicts[] = $message;

            $item = $itemsAtSlot->first();

            return array_merge($base, $this->effectRowFields($item, $package->name), [
                'status' => 'Conflict',
                'status_class' => 'conflict',
            ]);
        }

        /** @var EffectPackageItem $item */
        $item = $itemsAtSlot->first();
        $row = array_merge($base, $this->effectRowFields($item, $package->name));

        if (! $this->slotAllowedForItem($item, $slotNumber)) {
            $message = sprintf(
                '%s cannot use FX%d (%s only).',
                $this->displayName($item),
                $slotNumber,
                $item->x32Effect?->x32_slot_group?->allowedSlotsHelper()
                    ?? $item->effectDefinition?->x32_slot_group?->allowedSlotsHelper()
                    ?? 'invalid group',
            );
            $conflicts[] = $message;
            $row['status'] = 'Conflict';
            $row['status_class'] = 'conflict';

            return $row;
        }

        $slotReason = $this->slotAvailability->reasonForSlot($item, $slotNumber);

        if ($slotReason !== null) {
            $conflicts[] = $slotReason['message'];
            $row['status'] = 'Conflict';
            $row['status_class'] = 'conflict';

            if ($slotReason['reason'] === 'permanent_reservation') {
                $row['package_source'] = $this->extractPermanentPackageName($slotReason['message']) ?? $row['package_source'];
            }

            return $row;
        }

        $row['status'] = 'Ready';
        $row['status_class'] = 'ready';

        return $row;
    }

    /**
     * @return array<string, string>
     */
    private function effectRowFields(EffectPackageItem $item, string $packageName): array
    {
        return [
            'effect_name' => $this->displayName($item),
            'effect_code' => $item->x32Effect?->effect_code ?? $item->effectDefinition?->x32_algorithm_code ?? '—',
            'algorithm_id' => (string) ($item->x32Effect?->x32_algorithm_id ?? $item->effectDefinition?->x32_algorithm_id ?? '—'),
            'package_source' => $packageName,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function effectSummary(EffectPackageItem $item, string $packageName): array
    {
        return [
            'effect_name' => $this->displayName($item),
            'effect_code' => $item->x32Effect?->effect_code ?? $item->effectDefinition?->x32_algorithm_code ?? '—',
            'algorithm_id' => (string) ($item->x32Effect?->x32_algorithm_id ?? $item->effectDefinition?->x32_algorithm_id ?? '—'),
            'package_source' => $packageName,
        ];
    }

    private function slotAllowedForItem(EffectPackageItem $item, int $slotNumber): bool
    {
        $slotGroup = $item->x32Effect?->x32_slot_group ?? $item->effectDefinition?->x32_slot_group;

        if ($slotGroup === null) {
            return $slotNumber >= 1 && $slotNumber <= 8;
        }

        return in_array($slotNumber, $slotGroup->allowedSlotNumbers(), true);
    }

    /**
     * @return list<array<string, string>>
     */
    private function permanentReservationsForPackage(EffectPackage $package): array
    {
        if ($package->package_type === EffectPackageType::Permanent) {
            return [];
        }

        $reservations = [];

        foreach (range(1, 8) as $slotNumber) {
            $reservation = $this->findPermanentReservationForSlot($package, $slotNumber);

            if ($reservation !== null) {
                $reservations[] = $reservation;
            }
        }

        return $reservations;
    }

    /**
     * @return array<string, string>|null
     */
    private function findPermanentReservationForSlot(EffectPackage $package, int $slotNumber): ?array
    {
        if ($package->package_type === EffectPackageType::Permanent) {
            $occupant = EffectPackageItem::query()
                ->where('preferred_slot_number', $slotNumber)
                ->whereHas('effectPackage', fn ($query) => $query
                    ->where('is_active', true)
                    ->where('package_type', EffectPackageType::Permanent)
                    ->where('id', '!=', $package->id))
                ->with(['effectPackage', 'x32Effect', 'effectDefinition'])
                ->first();

            if ($occupant === null) {
                return null;
            }

            return [
                'slot_label' => 'FX'.$slotNumber,
                'package_name' => $occupant->effectPackage->name,
                'effect_name' => $this->displayName($occupant),
                'effect_code' => $occupant->x32Effect?->effect_code ?? $occupant->effectDefinition?->x32_algorithm_code ?? '—',
                'algorithm_id' => (string) ($occupant->x32Effect?->x32_algorithm_id ?? $occupant->effectDefinition?->x32_algorithm_id ?? '—'),
            ];
        }

        $occupant = EffectPackageItem::query()
            ->where('preferred_slot_number', $slotNumber)
            ->whereHas('effectPackage', fn ($query) => $query
                ->where('is_active', true)
                ->where('package_type', EffectPackageType::Permanent))
            ->with(['effectPackage', 'x32Effect', 'effectDefinition'])
            ->first();

        if ($occupant === null) {
            return null;
        }

        return [
            'slot_label' => 'FX'.$slotNumber,
            'package_name' => $occupant->effectPackage->name,
            'effect_name' => $this->displayName($occupant),
            'effect_code' => $occupant->x32Effect?->effect_code ?? $occupant->effectDefinition?->x32_algorithm_code ?? '—',
            'algorithm_id' => (string) ($occupant->x32Effect?->x32_algorithm_id ?? $occupant->effectDefinition?->x32_algorithm_id ?? '—'),
        ];
    }

    /**
     * @param  list<string>  $conflicts
     * @param  list<string>  $warnings
     */
    private function determineStatus(array $conflicts, array $warnings): EffectPackageDeploymentPlanStatus
    {
        if ($conflicts !== []) {
            return EffectPackageDeploymentPlanStatus::Blocked;
        }

        if ($warnings !== []) {
            return EffectPackageDeploymentPlanStatus::ReadyWithWarnings;
        }

        return EffectPackageDeploymentPlanStatus::Ready;
    }

    private function countFxSlotsUsed(EffectPackage $package): int
    {
        return $package->effectPackageItems
            ->filter(fn (EffectPackageItem $item) => $item->x32Effect?->countsTowardFxSlotLimit() ?? false)
            ->count();
    }

    private function displayName(EffectPackageItem $item): string
    {
        return $item->x32Effect?->displayName()
            ?? $item->effectDefinition?->name
            ?? 'Effect';
    }

    private function extractPermanentPackageName(string $message): ?string
    {
        if (preg_match('/permanent package (.+)\.$/', $message, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
