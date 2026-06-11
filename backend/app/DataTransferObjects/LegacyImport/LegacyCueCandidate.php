<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyCueCandidate
{
    public function __construct(
        public string $legacySongId,
        public string $songCode,
        public string $cueNumber,
        public int $sequenceOrder,
        public string $name,
        public ?int $legacyCueIndex,
        public bool $syntheticPreparation,
        public ?int $legacyBars = null,
    ) {}
}
