<?php

namespace App\Console\Commands;

use App\Services\CloudStudioMediaMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrateCloudStudioMediaToS3Command extends Command
{
    protected $signature = 'studio:migrate-media-to-s3
                            {--dry-run : Discover and report actions without copying or updating}
                            {--manifest= : Manifest output path (default: storage/app/media-migration/manifest-{timestamp}.jsonl)}';

    protected $description = 'Copy Cloud Studio media from local storage to esb-media (copy-only, resumable)';

    public function handle(CloudStudioMediaMigrationService $migration): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $manifestPath = $this->resolveManifestPath();
        File::ensureDirectoryExists(dirname($manifestPath));

        if ($dryRun) {
            $this->warn('Dry run — no copies or database updates will be performed.');
        }

        $entries = $migration->discover();
        $summary = [
            'discovered' => $entries->count(),
            'copied' => 0,
            'skipped_already_present' => 0,
            'missing_source' => 0,
            'failed' => 0,
            'db_updated' => 0,
            'db_already_canonical' => 0,
            'db_unchanged' => 0,
            'verified_s3' => 0,
            'serves_via_s3' => 0,
            'serves_via_local' => 0,
        ];

        $this->info('Discovered '.$summary['discovered'].' media reference(s).');
        $this->line('Manifest: '.$manifestPath);

        $handle = fopen($manifestPath, $dryRun && ! File::exists($manifestPath) ? 'w' : 'a');

        if ($handle === false) {
            $this->error('Unable to open manifest for writing: '.$manifestPath);

            return self::FAILURE;
        }

        foreach ($entries as $entry) {
            $row = $migration->migrateEntry($entry, $dryRun);
            fwrite($handle, json_encode($row, JSON_UNESCAPED_SLASHES)."\n");

            $this->line(sprintf(
                '[%s] %s:%s #%d — copy=%s db=%s',
                $row['media_type'],
                $row['table'],
                $row['column'],
                $row['record_id'],
                $row['s3_copy_status'],
                $row['db_update_status'],
            ));

            if ($row['error'] !== null) {
                $this->warn('  error: '.$row['error']);
            }

            match ($row['s3_copy_status']) {
                'copied' => $summary['copied']++,
                'already_present' => $summary['skipped_already_present']++,
                'missing_source' => $summary['missing_source']++,
                'failed' => $summary['failed']++,
                default => null,
            };

            match ($row['db_update_status']) {
                'updated', 'would_update' => $summary['db_updated']++,
                'already_canonical' => $summary['db_already_canonical']++,
                'unchanged', 'update_failed' => $summary['db_unchanged']++,
                default => null,
            };

            if ($row['verified_s3']) {
                $summary['verified_s3']++;
            }

            if ($row['serves_via_s3']) {
                $summary['serves_via_s3']++;
            }

            if ($row['serves_via_local']) {
                $summary['serves_via_local']++;
            }
        }

        fclose($handle);

        $summaryPath = preg_replace('/\.jsonl$/', '-summary.json', $manifestPath) ?? $manifestPath.'-summary.json';
        File::put($summaryPath, json_encode([
            'completed_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'manifest_path' => $manifestPath,
            'summary' => $summary,
            'notes' => [
                'copy_only' => true,
                'local_originals_deleted' => false,
                'compatibility_layer_removed' => false,
                'resumable' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info('Migration complete.');
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all(),
        );
        $this->line('Summary report: '.$summaryPath);
        $this->line('Local originals remain untouched. Dual-read compatibility layer unchanged.');

        return ($dryRun || $summary['failed'] === 0) ? self::SUCCESS : self::FAILURE;
    }

    private function resolveManifestPath(): string
    {
        $custom = $this->option('manifest');

        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return storage_path('app/media-migration/manifest-'.now()->format('Ymd-His').'.jsonl');
    }
}
