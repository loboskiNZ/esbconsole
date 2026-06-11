<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyMissingAsset
{
    public function __construct(
        public string $assetType,
        public string $legacySongId,
        public string $legacyPath,
        public ?string $roleSlug = null,
        public ?int $legacyCueIndex = null,
    ) {}
}
