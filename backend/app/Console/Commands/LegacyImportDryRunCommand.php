<?php

namespace App\Console\Commands;

use App\DataTransferObjects\LegacyImport\LegacyDryRunValidationStatus;
use App\DataTransferObjects\LegacyImport\LegacyImportConfig;
use App\Services\LegacyImport\LegacyDryRunValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LegacyImportDryRunCommand extends Command
{
    protected $signature = 'legacy:import-dry-run
                            {--root= : Legacy project root directory containing setlists.json}
                            {--setlist= : Optional explicit path to setlists.json}
                            {--musicians= : Optional explicit path to musicians.json}
                            {--setlist-id= : Legacy setlist id (defaults to activeSetlistId)}
                            {--band-slug=default-band : Band slug for expected storage reference paths}
                            {--output= : Optional path to write JSON report}
                            {--summary : Print human-readable summary after JSON output}';

    protected $description = 'Dry-run legacy show migration validation (no canonical writes, no asset copying)';

    public function __construct(
        private readonly LegacyDryRunValidationService $validationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $root = $this->option('root');

        if (! is_string($root) || $root === '') {
            $this->error('The --root option is required and must point to the legacy project directory.');

            return self::FAILURE;
        }

        $root = realpath($root) ?: $root;

        if (! is_dir($root)) {
            $this->error("Legacy root directory not found: {$root}");

            return self::FAILURE;
        }

        $config = LegacyImportConfig::fromPaths(
            projectRoot: $root,
            setlistsPath: $this->option('setlist') ?: null,
            musiciansPath: $this->option('musicians') ?: null,
            setlistId: $this->option('setlist-id') ?: null,
            bandSlug: $this->option('band-slug') ?: 'default-band',
        );

        if (! is_file($config->setlistsPath())) {
            $this->error("setlists.json not found at: {$config->setlistsPath()}");

            return self::FAILURE;
        }

        try {
            $report = $this->validationService->validate($config);
        } catch (\Throwable $e) {
            $this->error('Dry-run validation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $json = $report->toJson();

        $outputPath = $this->option('output');

        if (is_string($outputPath) && $outputPath !== '') {
            File::put($outputPath, $json);
            $this->info("Report written to {$outputPath}");
            $this->renderSummary($report);
        } else {
            $this->line($json);

            if ($this->option('summary')) {
                $this->renderSummary($report);
            }
        }

        return match ($report->status) {
            LegacyDryRunValidationStatus::PASS => self::SUCCESS,
            LegacyDryRunValidationStatus::PASS_WITH_WARNINGS => self::SUCCESS,
            LegacyDryRunValidationStatus::BLOCKED => self::INVALID,
            default => self::FAILURE,
        };
    }

    private function renderSummary(\App\DataTransferObjects\LegacyImport\LegacyDryRunValidationReport $report): void
    {
        $this->newLine();
        $this->info("Status: {$report->status}");
        $this->line("Show: {$report->showName} ({$report->legacySetlistId})");
        $this->line('Counts: '.json_encode($report->counts));
        $this->line('Blockers: '.count($report->issues['blockers']));
        $this->line('Warnings: '.count($report->issues['warnings']));
        $this->line('Missing charts: '.count($report->assetFindings['missing_chart_files']));
        $this->line('Missing snippets: '.count($report->assetFindings['missing_snippet_files']));
    }
}
