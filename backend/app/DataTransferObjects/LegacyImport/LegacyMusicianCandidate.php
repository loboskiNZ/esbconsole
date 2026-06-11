<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyMusicianCandidate
{
    public function __construct(
        public string $legacyMusicianId,
        public string $name,
        public ?string $email,
        public ?string $legacyRoleLabel,
    ) {}
}
