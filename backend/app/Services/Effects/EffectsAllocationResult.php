<?php

namespace App\Services\Effects;

use App\Enums\EffectsAllocationStatus;

readonly class EffectsAllocationResult
{
    /**
     * @param  list<array<string, mixed>>  $assignedPackages
     * @param  list<EffectsAllocatedEffect>  $allocatedEffects
     * @param  list<EffectsAllocatedEffect>  $nonSlotEffects
     * @param  list<EffectsAllocatedEffect>  $droppedOptionalEffects
     * @param  list<EffectsAllocationConflict>  $blockingConflicts
     * @param  list<string>  $warnings
     * @param  list<array<string, mixed>>  $fallbackConsoleRecall
     */
    public function __construct(
        public int $songId,
        public string $songName,
        public EffectsAllocationStatus $status,
        public array $assignedPackages,
        public array $allocatedEffects,
        public array $nonSlotEffects,
        public array $droppedOptionalEffects,
        public array $blockingConflicts,
        public array $warnings,
        public array $fallbackConsoleRecall,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'song_id' => $this->songId,
            'song_name' => $this->songName,
            'status' => $this->status->value,
            'assigned_packages' => $this->assignedPackages,
            'allocated_effects' => array_map(
                static fn (EffectsAllocatedEffect $effect): array => $effect->toArray(),
                $this->allocatedEffects,
            ),
            'non_slot_effects' => array_map(
                static fn (EffectsAllocatedEffect $effect): array => $effect->toArray(),
                $this->nonSlotEffects,
            ),
            'dropped_optional_effects' => array_map(
                static fn (EffectsAllocatedEffect $effect): array => $effect->toArray(),
                $this->droppedOptionalEffects,
            ),
            'blocking_conflicts' => array_map(
                static fn (EffectsAllocationConflict $conflict): array => $conflict->toArray(),
                $this->blockingConflicts,
            ),
            'warnings' => $this->warnings,
            'fallback_console_recall' => $this->fallbackConsoleRecall,
        ];
    }
}
