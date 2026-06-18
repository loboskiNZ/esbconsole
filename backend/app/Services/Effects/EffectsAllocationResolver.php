<?php

namespace App\Services\Effects;

use App\Enums\EffectImplementationType;
use App\Enums\EffectPackageType;
use App\Enums\EffectsAllocationStatus;
use App\Enums\X32SlotGroup;
use App\Models\EffectDefinition;
use App\Models\EffectPackage;
use App\Models\EffectPackageItem;
use App\Models\Song;
use App\Models\SongEffectAssignment;
use Illuminate\Support\Collection;

class EffectsAllocationResolver
{
    private const PACKAGE_SOURCE_PERMANENT = 'permanent';

    private const PACKAGE_SOURCE_SONG_ASSIGNMENT = 'song_assignment';

    public function resolve(Song $song): EffectsAllocationResult
    {
        $assignedPackages = $this->collectAssignedPackages($song);
        $candidates = $this->collectCandidates($assignedPackages);
        $sortedCandidates = $this->sortCandidates($candidates);

        $occupiedSlots = [];
        $allocatedEffects = [];
        $nonSlotEffects = [];
        $droppedOptionalEffects = [];
        $blockingConflicts = [];
        $warnings = [];

        foreach ($sortedCandidates as $candidate) {
            $effect = $this->buildAllocatedEffect($candidate);

            if (! $this->consumesFxSlot($candidate)) {
                $nonSlotEffects[] = $effect;

                continue;
            }

            $preferredSlot = $candidate['preferred_slot_number'];
            $slot = $this->allocateSlot($occupiedSlots, $candidate['slot_group'], $preferredSlot);

            if ($slot === null) {
                if ($candidate['is_required']) {
                    $blockingConflicts[] = new EffectsAllocationConflict(
                        effectDefinitionId: $candidate['definition']->id,
                        effectSlug: $candidate['definition']->slug,
                        effectName: $candidate['definition']->name,
                        packageSources: $candidate['package_sources'],
                        reason: 'No available FX slot in slot group '.$candidate['slot_group']->value,
                    );
                } else {
                    $droppedOptionalEffects[] = $effect;
                    $warnings[] = sprintf(
                        'Optional effect "%s" dropped — no available FX slot in group %s.',
                        $candidate['definition']->slug,
                        $candidate['slot_group']->value,
                    );
                }

                continue;
            }

            if ($preferredSlot !== null && $preferredSlot !== $slot) {
                $warnings[] = sprintf(
                    'Preferred slot %d unavailable for "%s"; allocated slot %d instead.',
                    $preferredSlot,
                    $candidate['definition']->slug,
                    $slot,
                );
            }

            $occupiedSlots[$slot] = $candidate['definition']->id;
            $allocatedEffects[] = new EffectsAllocatedEffect(
                effectDefinitionId: $effect->effectDefinitionId,
                effectName: $effect->effectName,
                effectSlug: $effect->effectSlug,
                packageSources: $effect->packageSources,
                slotNumber: $slot,
                slotGroup: $effect->slotGroup,
                x32AlgorithmCode: $effect->x32AlgorithmCode,
                x32AlgorithmId: $effect->x32AlgorithmId,
                implementationType: $effect->implementationType,
                tempoBehavior: $effect->tempoBehavior,
                safety: $effect->safety,
                parameterOverrides: $effect->parameterOverrides,
                timingRules: $effect->timingRules,
                consumesFxSlot: true,
            );
        }

        $fallbackConsoleRecall = $this->collectFallbackRecall($song, $warnings);

        $status = $this->determineStatus($blockingConflicts, $warnings);

        return new EffectsAllocationResult(
            songId: $song->id,
            songName: $song->name,
            status: $status,
            assignedPackages: $assignedPackages,
            allocatedEffects: $allocatedEffects,
            nonSlotEffects: $nonSlotEffects,
            droppedOptionalEffects: $droppedOptionalEffects,
            blockingConflicts: $blockingConflicts,
            warnings: $warnings,
            fallbackConsoleRecall: $fallbackConsoleRecall,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectAssignedPackages(Song $song): array
    {
        $packages = [];

        $permanentPackages = EffectPackage::query()
            ->where('is_active', true)
            ->where('package_type', EffectPackageType::Permanent)
            ->orderBy('priority')
            ->orderBy('id')
            ->with(['effectPackageItems.effectDefinition'])
            ->get();

        foreach ($permanentPackages as $package) {
            $packages[$package->id] = $this->formatAssignedPackage(
                $package,
                self::PACKAGE_SOURCE_PERMANENT,
                $package->priority,
            );
        }

        $assignments = SongEffectAssignment::query()
            ->where('song_id', $song->id)
            ->where('enabled', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->with(['effectPackage.effectPackageItems.effectDefinition'])
            ->get();

        foreach ($assignments as $assignment) {
            $package = $assignment->effectPackage;

            if ($package === null || ! $package->is_active) {
                continue;
            }

            if ($package->package_type === EffectPackageType::Permanent) {
                continue;
            }

            $packages[$package->id] = $this->formatAssignedPackage(
                $package,
                self::PACKAGE_SOURCE_SONG_ASSIGNMENT,
                $assignment->priority,
            );
        }

        return array_values($packages);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAssignedPackage(EffectPackage $package, string $source, int $priority): array
    {
        return [
            'package_id' => $package->id,
            'package_slug' => $package->slug,
            'package_name' => $package->name,
            'package_type' => $package->package_type->value,
            'source' => $source,
            'priority' => $priority,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $assignedPackages
     * @return list<array<string, mixed>>
     */
    private function collectCandidates(array $assignedPackages): array
    {
        $candidates = [];

        foreach ($assignedPackages as $assignedPackage) {
            $package = EffectPackage::query()
                ->with(['effectPackageItems.effectDefinition'])
                ->find($assignedPackage['package_id']);

            if ($package === null) {
                continue;
            }

            foreach ($package->effectPackageItems as $item) {
                $definition = $item->effectDefinition;

                if ($definition === null || ! $definition->is_active) {
                    continue;
                }

                $key = (string) $definition->id;

                if (! isset($candidates[$key])) {
                    $candidates[$key] = $this->newCandidate($definition, $item, $package, $assignedPackage);

                    continue;
                }

                $candidates[$key] = $this->mergeCandidate($candidates[$key], $item, $package, $assignedPackage);
            }
        }

        return array_values($candidates);
    }

    /**
     * @param  array<string, mixed>  $assignedPackage
     * @return array<string, mixed>
     */
    private function newCandidate(
        EffectDefinition $definition,
        EffectPackageItem $item,
        EffectPackage $package,
        array $assignedPackage,
    ): array {
        return [
            'definition' => $definition,
            'slot_group' => $definition->x32_slot_group,
            'is_required' => (bool) $item->is_required,
            'preferred_slot_number' => $item->preferred_slot_number,
            'parameter_overrides' => $item->parameter_overrides_json,
            'timing_rules' => $item->timing_rules_json,
            'package_sources' => [$package->slug],
            'package_tier' => $assignedPackage['source'] === self::PACKAGE_SOURCE_PERMANENT ? 0 : 1,
            'package_priority' => $assignedPackage['priority'],
            'item_priority' => $item->priority,
            'hybrid_slot_requested' => $definition->implementation_type === EffectImplementationType::Hybrid
                && $item->preferred_slot_number !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $assignedPackage
     * @return array<string, mixed>
     */
    private function mergeCandidate(
        array $existing,
        EffectPackageItem $item,
        EffectPackage $package,
        array $assignedPackage,
    ): array {
        if (! in_array($package->slug, $existing['package_sources'], true)) {
            $existing['package_sources'][] = $package->slug;
        }

        $existing['is_required'] = $existing['is_required'] || (bool) $item->is_required;

        $incomingTier = $assignedPackage['source'] === self::PACKAGE_SOURCE_PERMANENT ? 0 : 1;
        $incomingSortKey = [$incomingTier, $assignedPackage['priority'], $item->priority];
        $existingSortKey = [$existing['package_tier'], $existing['package_priority'], $existing['item_priority']];

        if ($incomingSortKey < $existingSortKey) {
            $existing['package_tier'] = $incomingTier;
            $existing['package_priority'] = $assignedPackage['priority'];
            $existing['item_priority'] = $item->priority;
            $existing['preferred_slot_number'] = $item->preferred_slot_number;
            $existing['parameter_overrides'] = $item->parameter_overrides_json ?? $existing['parameter_overrides'];
            $existing['timing_rules'] = $item->timing_rules_json ?? $existing['timing_rules'];
            $existing['hybrid_slot_requested'] = $existing['definition']->implementation_type === EffectImplementationType::Hybrid
                && $item->preferred_slot_number !== null;
        }

        return $existing;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function sortCandidates(array $candidates): array
    {
        usort($candidates, function (array $a, array $b): int {
            if ($a['is_required'] !== $b['is_required']) {
                return $a['is_required'] ? -1 : 1;
            }

            if ($a['package_tier'] !== $b['package_tier']) {
                return $a['package_tier'] <=> $b['package_tier'];
            }

            if ($a['package_priority'] !== $b['package_priority']) {
                return $a['package_priority'] <=> $b['package_priority'];
            }

            if ($a['item_priority'] !== $b['item_priority']) {
                return $a['item_priority'] <=> $b['item_priority'];
            }

            return $a['definition']->id <=> $b['definition']->id;
        });

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function consumesFxSlot(array $candidate): bool
    {
        $definition = $candidate['definition'];

        return match ($definition->implementation_type) {
            EffectImplementationType::FxSlot => true,
            EffectImplementationType::Hybrid => (bool) ($candidate['hybrid_slot_requested'] ?? false),
            EffectImplementationType::ChannelProcessing,
            EffectImplementationType::MainProcessing => false,
        };
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function buildAllocatedEffect(array $candidate): EffectsAllocatedEffect
    {
        $definition = $candidate['definition'];

        return new EffectsAllocatedEffect(
            effectDefinitionId: $definition->id,
            effectName: $definition->name,
            effectSlug: $definition->slug,
            packageSources: $candidate['package_sources'],
            slotNumber: null,
            slotGroup: $candidate['slot_group'],
            x32AlgorithmCode: $definition->x32_algorithm_code,
            x32AlgorithmId: $definition->x32_algorithm_id,
            implementationType: $definition->implementation_type,
            tempoBehavior: $definition->tempo_behavior,
            safety: $definition->active_song_safety,
            parameterOverrides: $candidate['parameter_overrides'],
            timingRules: $candidate['timing_rules'],
            consumesFxSlot: $this->consumesFxSlot($candidate),
        );
    }

    /**
     * @param  array<int, int>  $occupiedSlots
     */
    private function allocateSlot(array $occupiedSlots, X32SlotGroup $slotGroup, ?int $preferredSlot): ?int
    {
        $validSlots = $this->validSlotsForGroup($slotGroup);

        if ($preferredSlot !== null && in_array($preferredSlot, $validSlots, true) && ! isset($occupiedSlots[$preferredSlot])) {
            return $preferredSlot;
        }

        foreach ($validSlots as $slot) {
            if (! isset($occupiedSlots[$slot])) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function validSlotsForGroup(X32SlotGroup $slotGroup): array
    {
        return $slotGroup->allowedSlotNumbers();
    }

    /**
     * @param  list<string>  $warnings
     * @return list<array<string, mixed>>
     */
    private function collectFallbackRecall(Song $song, array &$warnings): array
    {
        $entries = SongEffectAssignment::query()
            ->where('song_id', $song->id)
            ->where('enabled', true)
            ->whereNotNull('fallback_console_recall_name')
            ->orderBy('priority')
            ->get()
            ->map(static fn (SongEffectAssignment $assignment): array => [
                'assignment_id' => $assignment->id,
                'effect_package_id' => $assignment->effect_package_id,
                'name' => $assignment->fallback_console_recall_name,
                'type' => $assignment->fallback_console_recall_type?->value,
                'priority' => $assignment->priority,
            ])
            ->values()
            ->all();

        if (count($entries) <= 1) {
            return $entries;
        }

        $signatures = Collection::make($entries)
            ->map(static fn (array $entry): string => ($entry['name'] ?? '').'|'.($entry['type'] ?? ''))
            ->unique();

        if ($signatures->count() > 1) {
            $warnings[] = 'Multiple conflicting fallback console recall entries exist for this song.';
        }

        return $entries;
    }

    /**
     * @param  list<EffectsAllocationConflict>  $blockingConflicts
     * @param  list<string>  $warnings
     */
    private function determineStatus(array $blockingConflicts, array $warnings): EffectsAllocationStatus
    {
        if ($blockingConflicts !== []) {
            return EffectsAllocationStatus::Blocked;
        }

        if ($warnings !== []) {
            return EffectsAllocationStatus::ReadyWithWarnings;
        }

        return EffectsAllocationStatus::Ready;
    }
}
