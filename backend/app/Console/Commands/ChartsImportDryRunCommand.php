<?php

namespace App\Console\Commands;

use App\DataTransferObjects\FolderChartImport\FolderChartImportConfig;
use App\Services\FolderChartImport\FolderChartImportPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ChartsImportDryRunCommand extends Command
{
    protected $signature = 'charts:import-dry-run
                            {--root=storage/app/imports/charts : Folder import root (relative to backend base path or absolute)}
                            {--band= : Target band id}
                            {--output= : Optional path to write JSON report}
                            {--summary : Print human-readable summary}';

    protected $description = 'Dry-run folder-based songs and charts import (no database or storage writes)';

    public function __construct(
        private readonly FolderChartImportPlanner $planner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $config = $this->resolveConfig(createMissingInstrumentParts: false);

        if ($config === null) {
            return self::FAILURE;
        }

        try {
            $report = $this->planner->plan($config, dryRun: true);
        } catch (\Throwable $e) {
            $this->error('Dry run failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $json = $report->toJson();
        $outputPath = $this->option('output');

        if (is_string($outputPath) && $outputPath !== '') {
            File::put($outputPath, $json);
            $this->info("Report written to {$outputPath}");
        } else {
            $this->line($json);
        }

        if ($this->option('summary')) {
            $this->renderSummary($report->summary, $report->unmatchedFilenames, $report->invalidFiles);
        }

        return self::SUCCESS;
    }

    private function resolveConfig(bool $createMissingInstrumentParts): ?FolderChartImportConfig
    {
        $bandOption = $this->option('band');

        if (! is_numeric($bandOption)) {
            $this->error('The --band option is required and must be a numeric band id.');

            return null;
        }

        $root = $this->resolveRootPath((string) $this->option('root'));

        if (! is_dir($root)) {
            $this->error("Import root directory not found: {$root}");

            return null;
        }

        return new FolderChartImportConfig(
            rootPath: $root,
            bandId: (int) $bandOption,
            createMissingInstrumentParts: $createMissingInstrumentParts,
        );
    }

    private function resolveRootPath(string $root): string
    {
        if ($root !== '' && $root[0] === DIRECTORY_SEPARATOR) {
            return $root;
        }

        return base_path($root);
    }

    /**
     * @param  array<string, int>  $summary
     * @param  list<array<string, mixed>>  $unmatched
     * @param  list<array<string, mixed>>  $invalid
     */
    private function renderSummary(array $summary, array $unmatched, array $invalid): void
    {
        $this->newLine();
        $this->info('Folder chart import dry-run summary');
        $this->line('Song folders found: '.($summary['song_folders_found'] ?? 0));
        $this->line('Songs existing: '.($summary['songs_existing'] ?? 0));
        $this->line('Songs would create: '.($summary['songs_would_create'] ?? 0));
        $this->line('Chart files found: '.($summary['chart_files_found'] ?? 0));
        $this->line('Charts would create: '.($summary['charts_would_create'] ?? 0));
        $this->line('Charts would reuse: '.($summary['charts_would_reuse'] ?? 0));
        $this->line('Instrument parts would create: '.($summary['instrument_parts_would_create'] ?? 0));
        $this->line('Unmatched filenames: '.($summary['unmatched_filenames'] ?? 0));
        $this->line('Invalid files: '.($summary['invalid_files'] ?? 0));
        $this->line('Duplicate checksum groups: '.($summary['duplicate_checksum_groups'] ?? 0));

        if ($unmatched !== []) {
            $this->newLine();
            $this->warn('Unmatched filenames:');
            foreach ($unmatched as $item) {
                $this->line('  - '.$item['relative_path'].' ('.$item['filename_stem'].')');
            }
        }

        if ($invalid !== []) {
            $this->newLine();
            $this->warn('Invalid files:');
            foreach ($invalid as $item) {
                $this->line('  - '.$item['relative_path'].' ('.$item['reason'].')');
            }
        }
    }
}
