<?php

namespace App\Services\FolderChartImport;

use App\DataTransferObjects\FolderChartImport\FolderChartFileCandidate;
use App\DataTransferObjects\FolderChartImport\FolderChartFilePlan;
use App\DataTransferObjects\FolderChartImport\FolderChartImportConfig;
use App\DataTransferObjects\FolderChartImport\FolderChartImportReport;
use App\DataTransferObjects\FolderChartImport\FolderSongImportPlan;
use App\Models\Band;
use App\Models\Chart;
use App\Models\InstrumentPart;
use App\Models\Song;

class FolderChartImportPlanner
{
    public function __construct(
        private readonly FolderChartImportScanner $scanner,
        private readonly ChartFilenameInstrumentMatcher $matcher,
    ) {}

    public function plan(FolderChartImportConfig $config, bool $dryRun = true): FolderChartImportReport
    {
        Band::query()->findOrFail($config->bandId);

        $scannedSongs = $this->scanner->scan($config);
        $songPlans = [];
        $unmatched = [];
        $invalid = [];
        $warnings = [];
        $duplicateGroups = [];

        $summary = [
            'song_folders_found' => count($scannedSongs),
            'songs_existing' => 0,
            'songs_would_create' => 0,
            'chart_files_found' => 0,
            'charts_would_create' => 0,
            'charts_would_reuse' => 0,
            'instrument_parts_existing' => 0,
            'instrument_parts_would_create' => 0,
            'unmatched_filenames' => 0,
            'invalid_files' => 0,
            'duplicate_checksum_groups' => 0,
        ];

        foreach ($scannedSongs as $scannedSong) {
            $folderName = $scannedSong['folder_name'];
            /** @var list<FolderChartFileCandidate> $files */
            $files = $scannedSong['files'];
            $normalizedTitle = $this->normalizeSongTitle($folderName);
            $legacyKey = $this->songLegacyKey($normalizedTitle);
            $existingSong = $this->findSongByTitle($config->bandId, $normalizedTitle);

            $existsInDatabase = $existingSong !== null;
            $wouldCreateSong = ! $existsInDatabase;

            if ($existsInDatabase) {
                $summary['songs_existing']++;
            } else {
                $summary['songs_would_create']++;
            }

            $checksumsInSong = [];
            $chartPlans = [];

            foreach ($files as $file) {
                $summary['chart_files_found']++;

                if (! $file->validExtension) {
                    $invalid[] = [
                        'relative_path' => $file->relativePath,
                        'reason' => 'unsupported_extension',
                        'extension' => $file->extension,
                    ];
                    $summary['invalid_files']++;
                    $chartPlans[] = $this->invalidChartPlan($file, $existingSong);

                    continue;
                }

                if ($file->checksum === null) {
                    $invalid[] = [
                        'relative_path' => $file->relativePath,
                        'reason' => 'unreadable_or_empty',
                    ];
                    $summary['invalid_files']++;
                    $chartPlans[] = $this->invalidChartPlan($file, $existingSong, 'unreadable_or_empty');

                    continue;
                }

                $instrumentMatch = $this->resolveInstrumentMatch($config, $file->filenameStem);
                $instrumentMatched = $instrumentMatch->matched;
                $instrumentPart = $instrumentMatched
                    ? InstrumentPart::query()
                        ->where('band_id', $config->bandId)
                        ->whereRaw('LOWER(name) = ?', [strtolower($instrumentMatch->canonicalName)])
                        ->first()
                    : null;

                if (! $instrumentMatched) {
                    $unmatched[] = [
                        'relative_path' => $file->relativePath,
                        'filename_stem' => $file->filenameStem,
                        'reason' => 'no_alias_or_catalog_match',
                    ];
                    $summary['unmatched_filenames']++;
                }

                $wouldCreateInstrumentPart = $instrumentMatched
                    && $instrumentPart === null
                    && $config->createMissingInstrumentParts;

                if ($instrumentPart !== null) {
                    $summary['instrument_parts_existing']++;
                } elseif ($wouldCreateInstrumentPart) {
                    $summary['instrument_parts_would_create']++;
                }

                $duplicateInSong = isset($checksumsInSong[$file->checksum]);
                if (! $duplicateInSong) {
                    $checksumsInSong[$file->checksum] = $file->relativePath;
                } else {
                    $duplicateGroups[] = [
                        'song_folder' => $folderName,
                        'checksum' => $file->checksum,
                        'first_file' => $checksumsInSong[$file->checksum],
                        'duplicate_file' => $file->relativePath,
                    ];
                }

                $existingChart = $existingSong !== null
                    ? Chart::query()
                        ->where('song_id', $existingSong->id)
                        ->where('checksum', $file->checksum)
                        ->first()
                    : null;

                $wouldReuse = $existingChart !== null;
                $wouldCreateChart = $instrumentMatched
                    && ! $wouldReuse
                    && ($instrumentPart !== null || $wouldCreateInstrumentPart);

                if ($wouldReuse) {
                    $summary['charts_would_reuse']++;
                } elseif ($wouldCreateChart) {
                    $summary['charts_would_create']++;
                }

                $expectedStorage = null;
                if ($instrumentMatched && $existingSong !== null) {
                    $expectedStorage = $this->expectedStorageReference(
                        $config->bandId,
                        $existingSong->song_code,
                        $instrumentMatch->slug ?? $this->matcher->slug($instrumentMatch->canonicalName),
                        $file->extension,
                    );
                } elseif ($instrumentMatched && $wouldCreateSong) {
                    $expectedStorage = 'charts/'.$config->bandId.'/{song_code}/'.($instrumentMatch->slug ?? 'part').'.'.$file->extension;
                }

                if ($duplicateInSong) {
                    $warnings[] = [
                        'type' => 'duplicate_checksum_in_song',
                        'song_folder' => $folderName,
                        'checksum' => $file->checksum,
                        'relative_path' => $file->relativePath,
                    ];
                }

                $chartPlans[] = new FolderChartFilePlan(
                    file: $file,
                    instrumentMatched: $instrumentMatched,
                    instrumentPartName: $instrumentMatch->canonicalName,
                    instrumentPartSlug: $instrumentMatch->slug,
                    instrumentMatchSource: $instrumentMatch->source,
                    instrumentPartExists: $instrumentPart !== null,
                    wouldCreateInstrumentPart: $wouldCreateInstrumentPart,
                    existingInstrumentPartId: $instrumentPart?->id,
                    duplicateChecksumInSong: $duplicateInSong,
                    duplicateChecksumKey: $file->checksum,
                    existingChartWouldReuse: $wouldReuse,
                    existingChartId: $existingChart?->id,
                    expectedStorageReference: $expectedStorage,
                    invalidFile: false,
                    invalidReason: null,
                );
            }

            $summary['duplicate_checksum_groups'] += count(array_unique(array_column(
                array_filter($duplicateGroups, fn (array $group) => $group['song_folder'] === $folderName),
                'checksum',
            )));

            $songPlans[] = new FolderSongImportPlan(
                folderName: $folderName,
                normalizedTitle: $normalizedTitle,
                legacyKey: $legacyKey,
                existsInDatabase: $existsInDatabase,
                existingSongId: $existingSong?->id,
                existingSongCode: $existingSong?->song_code,
                wouldCreate: $wouldCreateSong,
                charts: $chartPlans,
            );
        }

        $summary['duplicate_checksum_groups'] = count(array_unique(array_map(
            fn (array $group) => $group['song_folder'].'|'.$group['checksum'],
            $duplicateGroups,
        )));

        return new FolderChartImportReport(
            bandId: $config->bandId,
            rootPath: $config->rootPath,
            dryRun: $dryRun,
            songs: $songPlans,
            unmatchedFilenames: $unmatched,
            duplicateChecksumGroups: $duplicateGroups,
            invalidFiles: $invalid,
            warnings: $warnings,
            summary: $summary,
        );
    }

    public function normalizeSongTitle(string $folderName): string
    {
        return preg_replace('/\s+/u', ' ', trim($folderName)) ?? trim($folderName);
    }

    public function songLegacyKey(string $normalizedTitle): string
    {
        return 'folder_song:'.$this->matcher->slug($normalizedTitle);
    }

    public function chartLegacyKey(string $relativePath): string
    {
        return 'folder_chart:'.$relativePath;
    }

    public function expectedStorageReference(
        int $bandId,
        string $songCode,
        string $instrumentPartSlug,
        string $extension,
    ): string {
        return "charts/{$bandId}/{$songCode}/{$instrumentPartSlug}.{$extension}";
    }

    private function findSongByTitle(int $bandId, string $normalizedTitle): ?Song
    {
        return Song::query()
            ->where('band_id', $bandId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($normalizedTitle)])
            ->first();
    }

    private function resolveInstrumentMatch(
        FolderChartImportConfig $config,
        string $filenameStem,
    ): ChartFilenameMatch {
        $aliasMatch = $this->matcher->matchStem($filenameStem);

        if ($aliasMatch->matched) {
            return $aliasMatch;
        }

        $existing = InstrumentPart::query()
            ->where('band_id', $config->bandId)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($filenameStem))])
            ->first();

        if ($existing !== null) {
            return $this->matcher->matchExistingCatalogName($existing->name);
        }

        return $aliasMatch;
    }

    private function invalidChartPlan(
        FolderChartFileCandidate $file,
        ?Song $existingSong,
        ?string $reason = 'unsupported_extension',
    ): FolderChartFilePlan {
        return new FolderChartFilePlan(
            file: $file,
            instrumentMatched: false,
            instrumentPartName: null,
            instrumentPartSlug: null,
            instrumentMatchSource: 'invalid',
            instrumentPartExists: false,
            wouldCreateInstrumentPart: false,
            existingInstrumentPartId: null,
            duplicateChecksumInSong: false,
            duplicateChecksumKey: null,
            existingChartWouldReuse: false,
            existingChartId: null,
            expectedStorageReference: null,
            invalidFile: true,
            invalidReason: $reason,
        );
    }
}
