<?php

namespace App\DataTransferObjects\FolderChartImport;

final readonly class FolderChartImportConfig
{
    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    public function __construct(
        public string $rootPath,
        public int $bandId,
        public bool $createMissingInstrumentParts = false,
    ) {}

    public function legacyBatchKey(): string
    {
        return 'folder-import:'.hash('sha256', $this->rootPath);
    }
}
