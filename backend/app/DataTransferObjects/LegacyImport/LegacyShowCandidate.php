<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyShowCandidate
{
    public function __construct(
        public string $legacySetlistId,
        public string $name,
    ) {}
}
