<?php

namespace App\DataTransferObjects\FolderChartImport;

final readonly class FolderChartImportReport
{
    /**
     * @param  list<FolderSongImportPlan>  $songs
     * @param  list<array<string, mixed>>  $unmatchedFilenames
     * @param  list<array<string, mixed>>  $duplicateChecksumGroups
     * @param  list<array<string, mixed>>  $invalidFiles
     * @param  list<array<string, mixed>>  $warnings
     */
    public function __construct(
        public int $bandId,
        public string $rootPath,
        public bool $dryRun,
        public array $songs,
        public array $unmatchedFilenames,
        public array $duplicateChecksumGroups,
        public array $invalidFiles,
        public array $warnings,
        public array $summary,
    ) {}

    public function toArray(): array
    {
        return [
            'band_id' => $this->bandId,
            'root_path' => $this->rootPath,
            'dry_run' => $this->dryRun,
            'songs' => array_map(fn (FolderSongImportPlan $song) => [
                'folder_name' => $song->folderName,
                'normalized_title' => $song->normalizedTitle,
                'legacy_key' => $song->legacyKey,
                'exists_in_database' => $song->existsInDatabase,
                'existing_song_id' => $song->existingSongId,
                'existing_song_code' => $song->existingSongCode,
                'would_create' => $song->wouldCreate,
                'charts' => array_map(fn (FolderChartFilePlan $chart) => [
                    'relative_path' => $chart->file->relativePath,
                    'original_filename' => $chart->file->originalFilename,
                    'checksum' => $chart->file->checksum,
                    'file_size' => $chart->file->fileSize,
                    'mime_type' => $chart->file->mimeType,
                    'instrument_matched' => $chart->instrumentMatched,
                    'instrument_part_name' => $chart->instrumentPartName,
                    'instrument_match_source' => $chart->instrumentMatchSource,
                    'instrument_part_exists' => $chart->instrumentPartExists,
                    'would_create_instrument_part' => $chart->wouldCreateInstrumentPart,
                    'duplicate_checksum_in_song' => $chart->duplicateChecksumInSong,
                    'existing_chart_would_reuse' => $chart->existingChartWouldReuse,
                    'existing_chart_id' => $chart->existingChartId,
                    'expected_storage_reference' => $chart->expectedStorageReference,
                    'invalid_file' => $chart->invalidFile,
                    'invalid_reason' => $chart->invalidReason,
                ], $song->charts),
            ], $this->songs),
            'unmatched_filenames' => $this->unmatchedFilenames,
            'duplicate_checksum_groups' => $this->duplicateChecksumGroups,
            'invalid_files' => $this->invalidFiles,
            'warnings' => $this->warnings,
            'summary' => $this->summary,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
