<?php

namespace App\Services\FolderChartImport;

use App\DataTransferObjects\FolderChartImport\FolderChartFilePlan;
use App\DataTransferObjects\FolderChartImport\FolderChartImportConfig;
use App\DataTransferObjects\FolderChartImport\FolderChartImportReport;
use App\DataTransferObjects\FolderChartImport\FolderSongImportPlan;
use App\Models\Chart;
use App\Models\ImportBatch;
use App\Models\ImportEntityMapping;
use App\Models\InstrumentPart;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use App\Services\SongCodeAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FolderChartImportCommitService
{
    public function __construct(
        private readonly FolderChartImportPlanner $planner,
        private readonly SongCodeAllocator $songCodeAllocator,
        private readonly ChartFilenameInstrumentMatcher $matcher,
    ) {}

    public function commit(FolderChartImportConfig $config): FolderChartImportReport
    {
        $plan = $this->planner->plan($config, dryRun: false);

        $batch = ImportBatch::query()->create([
            'band_id' => $config->bandId,
            'legacy_setlist_id' => $config->legacyBatchKey(),
            'status' => ImportBatch::STATUS_COMMITTED,
            'manifest_json' => [
                'source' => 'folder_chart_import',
                'root_path' => $config->rootPath,
                'create_missing_instrument_parts' => $config->createMissingInstrumentParts,
            ],
            'started_at' => now(),
        ]);

        $commitSummary = [
            'songs_created' => 0,
            'songs_matched' => 0,
            'charts_created' => 0,
            'charts_reused' => 0,
            'charts_skipped' => 0,
            'instrument_parts_created' => 0,
            'instrument_parts_matched' => 0,
            'song_instrument_parts_linked' => 0,
            'files_copied' => 0,
            'unmatched_skipped' => count($plan->unmatchedFilenames),
            'invalid_skipped' => count($plan->invalidFiles),
        ];

        DB::transaction(function () use ($config, $plan, $batch, &$commitSummary): void {
            foreach ($plan->songs as $songPlan) {
                $song = $this->resolveSong($config, $songPlan, $batch, $commitSummary);

                foreach ($songPlan->charts as $chartPlan) {
                    $this->importChartFile($config, $song, $chartPlan, $batch, $commitSummary);
                }
            }
        });

        $batch->update([
            'report_json' => [
                'plan_summary' => $plan->summary,
                'commit_summary' => $commitSummary,
            ],
            'completed_at' => now(),
        ]);

        $finalPlan = $this->planner->plan($config, dryRun: false);
        $mergedSummary = array_merge($finalPlan->summary, ['commit' => $commitSummary]);

        return new FolderChartImportReport(
            bandId: $config->bandId,
            rootPath: $config->rootPath,
            dryRun: false,
            songs: $finalPlan->songs,
            unmatchedFilenames: $finalPlan->unmatchedFilenames,
            duplicateChecksumGroups: $finalPlan->duplicateChecksumGroups,
            invalidFiles: $finalPlan->invalidFiles,
            warnings: $finalPlan->warnings,
            summary: $mergedSummary,
        );
    }

    /**
     * @param  array<string, int>  $commitSummary
     */
    private function resolveSong(
        FolderChartImportConfig $config,
        FolderSongImportPlan $songPlan,
        ImportBatch $batch,
        array &$commitSummary,
    ): Song {
        if ($songPlan->existingSongId !== null) {
            $song = Song::query()->findOrFail($songPlan->existingSongId);
            $commitSummary['songs_matched']++;

            $this->recordMapping($batch, 'song', $songPlan->legacyKey, $song);

            return $song;
        }

        $band = \App\Models\Band::query()->findOrFail($config->bandId);

        $song = Song::query()->create([
            'band_id' => $config->bandId,
            'song_code' => $this->songCodeAllocator->nextForBand($band),
            'name' => $songPlan->normalizedTitle,
            'status' => Song::STATUS_IN_PROGRESS,
        ]);

        $commitSummary['songs_created']++;
        $this->recordMapping($batch, 'song', $songPlan->legacyKey, $song);

        return $song;
    }

    /**
     * @param  array<string, int>  $commitSummary
     */
    private function importChartFile(
        FolderChartImportConfig $config,
        Song $song,
        FolderChartFilePlan $chartPlan,
        ImportBatch $batch,
        array &$commitSummary,
    ): void {
        if ($chartPlan->invalidFile || ! $chartPlan->instrumentMatched || $chartPlan->instrumentPartName === null) {
            $commitSummary['charts_skipped']++;

            return;
        }

        $instrumentPart = $this->resolveInstrumentPart($config, $chartPlan, $batch, $commitSummary);

        if ($instrumentPart === null) {
            $commitSummary['charts_skipped']++;

            return;
        }

        $checksum = $chartPlan->file->checksum;

        if ($checksum === null) {
            $commitSummary['charts_skipped']++;

            return;
        }

        $chart = Chart::query()
            ->where('song_id', $song->id)
            ->where('checksum', $checksum)
            ->first();

        if ($chart === null) {
            $slug = $chartPlan->instrumentPartSlug ?? $this->matcher->slug($chartPlan->instrumentPartName);
            $storageReference = $this->planner->expectedStorageReference(
                $config->bandId,
                $song->song_code,
                $slug,
                $chartPlan->file->extension,
            );

            $this->ensureStorageDirectory($storageReference);
            File::copy($chartPlan->file->absolutePath, Storage::disk('local')->path($storageReference));

            $chart = Chart::query()->create([
                'song_id' => $song->id,
                'title' => $chartPlan->file->filenameStem,
                'original_filename' => $chartPlan->file->originalFilename,
                'storage_reference' => $storageReference,
                'checksum' => $checksum,
                'mime_type' => $chartPlan->file->mimeType,
                'file_size' => $chartPlan->file->fileSize,
                'import_batch_id' => $batch->id,
            ]);

            $commitSummary['charts_created']++;
            $commitSummary['files_copied']++;
        } else {
            $commitSummary['charts_reused']++;
        }

        $this->recordMapping(
            $batch,
            'chart',
            $this->planner->chartLegacyKey($chartPlan->file->relativePath),
            $chart,
        );

        $sip = SongInstrumentPart::query()->firstOrCreate(
            [
                'song_id' => $song->id,
                'instrument_part_id' => $instrumentPart->id,
            ],
            ['notes' => null],
        );

        if ($sip->chart_id !== $chart->id) {
            $sip->update(['chart_id' => $chart->id]);
        }

        $commitSummary['song_instrument_parts_linked']++;

        $this->recordMapping(
            $batch,
            'song_instrument_part',
            'folder_sip:'.$song->id.':'.$instrumentPart->id,
            $sip,
        );
    }

    /**
     * @param  array<string, int>  $commitSummary
     */
    private function resolveInstrumentPart(
        FolderChartImportConfig $config,
        FolderChartFilePlan $chartPlan,
        ImportBatch $batch,
        array &$commitSummary,
    ): ?InstrumentPart {
        $name = $chartPlan->instrumentPartName;

        $existing = InstrumentPart::query()
            ->where('band_id', $config->bandId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($existing !== null) {
            $commitSummary['instrument_parts_matched']++;
            $this->recordMapping($batch, 'instrument_part', 'folder_part:'.$this->matcher->slug($name), $existing);

            return $existing;
        }

        if (! $config->createMissingInstrumentParts || $chartPlan->instrumentMatchSource !== 'alias') {
            return null;
        }

        $created = InstrumentPart::query()->create([
            'band_id' => $config->bandId,
            'name' => $name,
            'active' => true,
        ]);

        $commitSummary['instrument_parts_created']++;
        $this->recordMapping($batch, 'instrument_part', 'folder_part:'.$this->matcher->slug($name), $created);

        return $created;
    }

    private function ensureStorageDirectory(string $storageReference): void
    {
        $directory = dirname(Storage::disk('local')->path($storageReference));
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function recordMapping(
        ImportBatch $batch,
        string $entityType,
        string $legacyKey,
        Song|Chart|SongInstrumentPart|InstrumentPart $entity,
    ): void {
        ImportEntityMapping::query()->updateOrCreate(
            [
                'import_batch_id' => $batch->id,
                'entity_type' => $entityType,
                'legacy_key' => $legacyKey,
            ],
            [
                'entity_id' => $entity->id,
                'public_id' => $entity->public_id ?? null,
            ],
        );
    }
}
