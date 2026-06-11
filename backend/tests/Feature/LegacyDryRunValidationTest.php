<?php

namespace Tests\Feature;

use App\DataTransferObjects\LegacyImport\LegacyDryRunValidationStatus;
use App\DataTransferObjects\LegacyImport\LegacyImportConfig;
use App\Models\Chart;
use App\Models\Cue;
use App\Models\ImportBatch;
use App\Models\InstrumentPart;
use App\Models\Show;
use App\Models\Snippet;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use App\Services\LegacyImport\LegacyDryRunValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LegacyDryRunValidationTest extends TestCase
{
    use RefreshDatabase;

    private function minimalConfig(): LegacyImportConfig
    {
        return new LegacyImportConfig(
            projectRoot: base_path('tests/fixtures/legacy/minimal'),
            bandSlug: 'fixture-band',
        );
    }

    public function test_dry_run_report_can_be_generated_from_fixture(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertSame('Fixture Show', $report->showName);
        $this->assertSame('default', $report->legacySetlistId);
        $this->assertNotEmpty($report->importBatchId);
    }

    public function test_dry_run_counts_are_correct(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertSame(1, $report->counts['setlists']);
        $this->assertSame(3, $report->counts['songs']);
        $this->assertSame(3, $report->counts['playlist_items']);
        $this->assertSame(3, $report->counts['synthetic_cue_000']);
        $this->assertGreaterThan(0, $report->counts['chart_candidates']);
        $this->assertGreaterThan(0, $report->counts['snippet_candidates']);
        $this->assertSame(1, $report->counts['musician_candidates']);
    }

    public function test_missing_assets_are_surfaced_in_report(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertNotEmpty($report->assetFindings['missing_snippet_files']);
        $this->assertNotEmpty($report->issues['missing_files']['snippets']);
    }

    public function test_zero_cue_songs_are_reported(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertContains('100003', $report->issues['zero_cue_songs']);
    }

    public function test_role_normalization_appears_in_report(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertArrayHasKey('trumpet', $report->mappingFindings['role_string_to_instrument_part']);
        $this->assertSame('Trumpet', $report->mappingFindings['role_string_to_instrument_part']['trumpet']['normalized_name']);
    }

    public function test_shared_chart_candidates_are_reported(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertNotEmpty($report->assetFindings['shared_chart_candidates']);

        $shared = $report->assetFindings['shared_chart_candidates'][0];
        $this->assertContains('machines', $shared['assigned_role_slugs']);
        $this->assertContains('singer', $shared['assigned_role_slugs']);
    }

    public function test_validation_status_is_pass_with_warnings_for_non_blocking_issues(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertSame(LegacyDryRunValidationStatus::PASS_WITH_WARNINGS, $report->status);
        $this->assertEmpty($report->issues['blockers']);
        $this->assertNotEmpty($report->issues['warnings']);
    }

    public function test_validation_status_is_blocked_for_critical_blockers(): void
    {
        $config = new LegacyImportConfig(
            projectRoot: base_path('tests/fixtures/legacy/blocked'),
        );

        $report = app(LegacyDryRunValidationService::class)->validate($config);

        $this->assertSame(LegacyDryRunValidationStatus::BLOCKED, $report->status);
        $this->assertNotEmpty($report->issues['blockers']);
    }

    public function test_validation_status_is_pass_when_no_issues(): void
    {
        $config = new LegacyImportConfig(
            projectRoot: base_path('tests/fixtures/legacy/clean-pass'),
        );

        $report = app(LegacyDryRunValidationService::class)->validate($config);

        $this->assertSame(LegacyDryRunValidationStatus::PASS, $report->status);
    }

    public function test_dry_run_does_not_write_canonical_entities(): void
    {
        $this->assertSame(0, Song::query()->count());
        $this->assertSame(0, ImportBatch::query()->count());

        app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $this->assertSame(0, Song::query()->count());
        $this->assertSame(0, Show::query()->count());
        $this->assertSame(0, Cue::query()->count());
        $this->assertSame(0, Chart::query()->count());
        $this->assertSame(0, Snippet::query()->count());
        $this->assertSame(0, SongInstrumentPart::query()->count());
        $this->assertSame(0, InstrumentPart::query()->count());
        $this->assertSame(0, ImportBatch::query()->count());
    }

    public function test_json_output_is_valid_and_contains_required_sections(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());
        $decoded = json_decode($report->toJson(), true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('status', $decoded);
        $this->assertArrayHasKey('counts', $decoded);
        $this->assertArrayHasKey('asset_findings', $decoded);
        $this->assertArrayHasKey('mapping_findings', $decoded);
        $this->assertArrayHasKey('issues', $decoded);

        $this->assertArrayHasKey('legacy_song_id_to_song_code', $decoded['mapping_findings']);
        $this->assertArrayHasKey('legacy_cue_index_to_cue_number', $decoded['mapping_findings']);
        $this->assertArrayHasKey('snippet_to_sip_cue', $decoded['mapping_findings']);
        $this->assertArrayHasKey('shared_chart_candidates', $decoded['asset_findings']);
    }

    public function test_artisan_dry_run_command_outputs_json_report(): void
    {
        $exitCode = Artisan::call('legacy:import-dry-run', [
            '--root' => base_path('tests/fixtures/legacy/minimal'),
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame(LegacyDryRunValidationStatus::PASS_WITH_WARNINGS, $decoded['status']);
    }

    public function test_artisan_dry_run_command_writes_output_file(): void
    {
        $outputPath = storage_path('framework/testing/legacy-dry-run-report.json');

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        $exitCode = Artisan::call('legacy:import-dry-run', [
            '--root' => base_path('tests/fixtures/legacy/minimal'),
            '--output' => $outputPath,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($outputPath);

        $decoded = json_decode((string) file_get_contents($outputPath), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('counts', $decoded);

        unlink($outputPath);
    }

    public function test_snippet_to_sip_cue_mapping_in_report(): void
    {
        $report = app(LegacyDryRunValidationService::class)->validate($this->minimalConfig());

        $mappings = $report->mappingFindings['snippet_to_sip_cue'];
        $this->assertNotEmpty($mappings);

        $first = array_values($mappings)[0];
        $this->assertArrayHasKey('cue_number', $first);
        $this->assertArrayHasKey('normalized_instrument_part', $first);
        $this->assertSame('chart_crop', $first['source_type']);
    }
}
