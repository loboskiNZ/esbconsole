<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyInstrumentPartCandidate
{
    public function __construct(
        public string $legacyRoleSlug,
        public string $normalizedName,
        public string $catalogKey,
    ) {}
}
