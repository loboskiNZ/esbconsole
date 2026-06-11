<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacySnippetCandidate
{
    /**
     * @param  array<string, mixed>  $sourceMetadata
     */
    public function __construct(
        public string $candidateKey,
        public string $legacySongId,
        public string $songCode,
        public string $legacyRoleSlug,
        public string $normalizedInstrumentPartName,
        public int $legacyCueIndex,
        public string $cueNumber,
        public string $sourceType,
        public string $legacyPath,
        public ?string $physicalPath,
        public ?string $checksum,
        public string $expectedStorageReference,
        public bool $fileExists,
        public ?string $chartCandidateKey,
        public array $sourceMetadata,
    ) {}
}
