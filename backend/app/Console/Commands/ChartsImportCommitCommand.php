<?php

namespace App\Console\Commands;

use App\DataTransferObjects\FolderChartImport\FolderChartImportConfig;
use App\Services\FolderChartImport\FolderChartImportCommitService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ChartsImportCommitCommand extends Command
{
    protected $signature = 'charts:import-commit
                            {--root=storage/app/imports/charts : Folder import root (relative to backend base path or absolute)}
                            {--band= : Target band id}
                            {--create-missing-instrument-parts : Create InstrumentPart rows for alias-matched filenames when missing}
                            {--output= : Optional path to write JSON report}
                            {--summary : Print human-readable summary}';

    protected $description = 'Import songs, charts, and song instrument parts from a folder tree';

    public function __construct(
        private readonly FolderChartImportCommitService $commitService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $bandOption = $this->option('band');

        if (! is_numeric($bandOption)) {
            $this->error('The --band option is required and must be a numeric band id.');

            return self::FAILURE;
        }

        $root = $this->resolveRootPath((string) $this->option('root'));

        if (! is_dir($root)) {
            $this->error("Import root directory not found: {$root}");

            return self::FAILURE;
        }

        $config = new FolderChartImportConfig(
            rootPath: $root,
            bandId: (int) $bandOption,
            createMissingInstrumentParts: (bool) $this->option('create-missing-instrument-parts'),
        );

        if (! $config->createMissingInstrumentParts) {
            $this->warn('Instrument parts are matched only when they already exist unless --create-missing-instrument-parts is supplied.');
        }

        try {
            $report = $this->commitService->commit($config);
        } catch (\Throwable $e) {
            $this->error('Import commit failed: '.$e->getMessage());

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
            $this->renderSummary($report->summary);
        }

        return self::SUCCESS;
    }

    private function resolveRootPath(string $root): string
    {
        if ($root !== '' && $root[0] === DIRECTORY_SEPARATOR) {
            return $root;
        }

        return base_path($root);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function renderSummary(array $summary): void
    {
        $this->newLine();
        $this->info('Folder chart import commit summary');

        if (isset($summary['commit']) && is_array($summary['commit'])) {
            foreach ($summary['commit'] as $key => $value) {
                $this->line(str_replace('_', ' ', $key).': '.$value);
            }

            return;
        }

        foreach ($summary as $key => $value) {
            if (! is_array($value)) {
                $this->line(str_replace('_', ' ', (string) $key).': '.$value);
            }
        }
    }
}
