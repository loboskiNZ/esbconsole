<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacySongInstrumentPartCandidate
{
    /**
     * @param  list<string>  $legacyRoleSlugs
     */
    public function __construct(
        public string $legacySongId,
        public string $songCode,
        public string $legacyRoleSlug,
        public string $normalizedInstrumentPartName,
        public ?string $chartCandidateKey,
        public string $candidateKey,
    ) {}
}
