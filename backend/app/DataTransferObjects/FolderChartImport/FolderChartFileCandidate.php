<?php

namespace App\DataTransferObjects\FolderChartImport;

final readonly class FolderChartFileCandidate
{
    public function __construct(
        public string $songFolderName,
        public string $relativePath,
        public string $absolutePath,
        public string $originalFilename,
        public string $filenameStem,
        public string $extension,
        public ?string $checksum,
        public int $fileSize,
        public ?string $mimeType,
        public bool $validExtension,
    ) {}
}
