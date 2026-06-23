<?php

namespace App\DataTransferObjects\FolderChartImport;

final readonly class FolderSongImportPlan
{
    public function __construct(
        public string $folderName,
        public string $normalizedTitle,
        public string $legacyKey,
        public bool $existsInDatabase,
        public ?int $existingSongId,
        public ?string $existingSongCode,
        public bool $wouldCreate,
        /** @var list<FolderChartFilePlan> */
        public array $charts,
    ) {}
}
