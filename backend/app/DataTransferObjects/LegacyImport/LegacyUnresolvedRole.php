<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyUnresolvedRole
{
    public function __construct(
        public string $legacySongId,
        public string $legacyRoleSlug,
        public string $reason,
    ) {}
}
