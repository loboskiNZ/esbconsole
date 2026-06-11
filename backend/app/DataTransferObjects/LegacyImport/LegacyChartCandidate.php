<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyChartCandidate
{
    /**
     * @param  list<string>  $assignedRoleSlugs
     */
    public function __construct(
        public string $candidateKey,
        public string $legacySongId,
        public string $songCode,
        public string $legacyPath,
        public ?string $checksum,
        public string $title,
        public string $expectedStorageReference,
        public array $assignedRoleSlugs,
        public string $source,
        public bool $fileExists,
        public bool $skippedPlaceholder,
    ) {}
}
