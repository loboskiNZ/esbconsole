<?php

namespace App\DataTransferObjects\FolderChartImport;

final readonly class FolderChartFilePlan
{
    public function __construct(
        public FolderChartFileCandidate $file,
        public bool $instrumentMatched,
        public ?string $instrumentPartName,
        public ?string $instrumentPartSlug,
        public string $instrumentMatchSource,
        public bool $instrumentPartExists,
        public bool $wouldCreateInstrumentPart,
        public ?int $existingInstrumentPartId,
        public bool $duplicateChecksumInSong,
        public ?string $duplicateChecksumKey,
        public bool $existingChartWouldReuse,
        public ?int $existingChartId,
        public ?string $expectedStorageReference,
        public bool $invalidFile,
        public ?string $invalidReason,
    ) {}
}
