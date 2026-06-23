<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Chart;
use App\Models\ImportBatch;
use App\Models\ImportEntityMapping;
use App\Models\InstrumentPart;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use App\Services\FolderChartImport\ChartFilenameInstrumentMatcher;
use App\Services\FolderChartImport\FolderChartImportPlanner;
use App\Services\FolderChartImport\FolderChartImportScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesFolderChartFixtures;
use Tests\TestCase;

class FolderChartImportTest extends TestCase
{
    use CreatesFolderChartFixtures;
    use RefreshDatabase;

    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = storage_path('framework/testing/folder-chart-import-'.uniqid('', true));
        File::deleteDirectory($this->fixtureRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    public function test_scanner_discovers_song_folders_and_chart_files(): void
    {
        $this->createFolderChartFixture($this->fixtureRoot, [
            'Shadow Waltz' => [
                'Vocal.pdf' => $this->minimalPdfContents('vocal'),
                'Guitar.pdf' => $this->minimalPdfContents('guitar'),
            ],
        ]);

        File::put($this->fixtureRoot.'/ignored-root.pdf', $this->minimalPdfContents('root'));

        $scanner = app(FolderChartImportScanner::class);
        $songs = $scanner->scan(new \App\DataTransferObjects\FolderChartImport\FolderChartImportConfig(
            rootPath: $this->fixtureRoot,
            bandId: 1,
        ));

        $this->assertCount(1, $songs);
        $this->assertSame('Shadow Waltz', $songs[0]['folder_name']);
        $this->assertCount(2, $songs[0]['files']);
    }

    public function test_instrument_matcher_normalizes_filename_stems(): void
    {
        $matcher = app(ChartFilenameInstrumentMatcher::class);

        $this->assertSame('Vocals', $matcher->matchStem('Vocal')->canonicalName);
        $this->assertSame('Horns', $matcher->matchStem('Horns')->canonicalName);
        $this->assertSame('Backing Vocals', $matcher->matchStem('BVs')->canonicalName);
        $this->assertSame('Alto Sax', $matcher->matchStem('BAND Man of constant sorrow - Alto Sax')->canonicalName);
        $this->assertSame('Trombone', $matcher->matchStem('Reggaeton De Otepoti (TROMBONE)')->canonicalName);
        $this->assertSame('Bass', $matcher->matchStem('Band electronic love - Bass Guitar')->canonicalName);
        $this->assertFalse($matcher->matchStem('Mystery Part')->matched);
        $this->assertFalse($matcher->matchStem('BAND havent found - Full Score')->matched);
    }

    public function test_dry_run_reports_without_database_or_storage_writes(): void
    {
        $band = Band::factory()->create();
        $this->createFolderChartFixture($this->fixtureRoot, [
            'Existing Song' => [
                'Vocal.pdf' => $this->minimalPdfContents('vocal'),
                'Mystery.pdf' => $this->minimalPdfContents('mystery'),
                'Notes.txt' => 'not a chart',
            ],
        ]);

        $songCount = Song::query()->count();
        $chartCount = Chart::query()->count();
        $batchCount = ImportBatch::query()->count();

        $exitCode = Artisan::call('charts:import-dry-run', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--summary' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($songCount, Song::query()->count());
        $this->assertSame($chartCount, Chart::query()->count());
        $this->assertSame($batchCount, ImportBatch::query()->count());
        $this->assertStringContainsString('Unmatched filenames', Artisan::output());
        $this->assertStringNotContainsString('Readiness score', Artisan::output());
        $this->assertStringNotContainsString('Profile completeness', Artisan::output());
    }

    public function test_commit_import_creates_songs_charts_and_song_instrument_parts(): void
    {
        Storage::fake('local');

        $band = Band::factory()->create();
        $vocalPdf = $this->minimalPdfContents('vocal-a');
        $guitarPdf = $this->minimalPdfContents('guitar-a');

        $this->createFolderChartFixture($this->fixtureRoot, [
            'Opening Number' => [
                'Vocal.pdf' => $vocalPdf,
                'Guitar.pdf' => $guitarPdf,
            ],
        ]);

        InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Vocals']);
        InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Guitar']);

        $exitCode = Artisan::call('charts:import-commit', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--summary' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $song = Song::query()->where('band_id', $band->id)->where('name', 'Opening Number')->first();
        $this->assertNotNull($song);
        $this->assertMatchesRegularExpression('/^\d{3}$/', $song->song_code);

        $this->assertSame(2, Chart::query()->where('song_id', $song->id)->count());
        $this->assertSame(2, SongInstrumentPart::query()->where('song_id', $song->id)->count());

        $vocalChart = Chart::query()
            ->where('song_id', $song->id)
            ->where('original_filename', 'Vocal.pdf')
            ->first();

        $this->assertNotNull($vocalChart);
        $this->assertSame('Vocal', $vocalChart->title);
        $this->assertSame('application/pdf', $vocalChart->mime_type);
        $this->assertSame(strlen($vocalPdf), $vocalChart->file_size);
        $this->assertStringStartsWith("charts/{$band->id}/{$song->song_code}/", $vocalChart->storage_reference);
        Storage::disk('local')->assertExists($vocalChart->storage_reference);

        $vocalSip = SongInstrumentPart::query()
            ->where('song_id', $song->id)
            ->whereHas('instrumentPart', fn ($q) => $q->where('name', 'Vocals'))
            ->first();

        $this->assertNotNull($vocalSip);
        $this->assertSame($vocalChart->id, $vocalSip->chart_id);
    }

    public function test_commit_with_create_missing_instrument_parts_flag(): void
    {
        Storage::fake('local');

        $band = Band::factory()->create();

        $this->createFolderChartFixture($this->fixtureRoot, [
            'New Song' => [
                'Drums.pdf' => $this->minimalPdfContents('drums'),
            ],
        ]);

        Artisan::call('charts:import-commit', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--create-missing-instrument-parts' => true,
        ]);

        $this->assertDatabaseHas('instrument_parts', [
            'band_id' => $band->id,
            'name' => 'Drums',
        ]);

        $song = Song::query()->where('name', 'New Song')->firstOrFail();
        $this->assertSame(1, SongInstrumentPart::query()->where('song_id', $song->id)->count());
    }

    public function test_commit_is_idempotent_on_second_run(): void
    {
        Storage::fake('local');

        $band = Band::factory()->create();
        InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Bass']);

        $this->createFolderChartFixture($this->fixtureRoot, [
            'Groove Track' => [
                'Bass.pdf' => $this->minimalPdfContents('bass'),
            ],
        ]);

        Artisan::call('charts:import-commit', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--create-missing-instrument-parts' => true,
        ]);

        $songCount = Song::query()->count();
        $chartCount = Chart::query()->count();
        $sipCount = SongInstrumentPart::query()->count();

        Artisan::call('charts:import-commit', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--create-missing-instrument-parts' => true,
        ]);

        $this->assertSame($songCount, Song::query()->count());
        $this->assertSame($chartCount, Chart::query()->count());
        $this->assertSame($sipCount, SongInstrumentPart::query()->count());
    }

    public function test_duplicate_checksum_within_song_reuses_chart(): void
    {
        Storage::fake('local');

        $band = Band::factory()->create();
        InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Vocals']);
        InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Guitar']);

        $shared = $this->minimalPdfContents('shared');

        $this->createFolderChartFixture($this->fixtureRoot, [
            'Shared Chart Song' => [
                'Vocal.pdf' => $shared,
                'Guitar.pdf' => $shared,
            ],
        ]);

        Artisan::call('charts:import-commit', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--create-missing-instrument-parts' => true,
        ]);

        $song = Song::query()->where('name', 'Shared Chart Song')->firstOrFail();
        $this->assertSame(1, Chart::query()->where('song_id', $song->id)->count());

        $chart = Chart::query()->where('song_id', $song->id)->firstOrFail();
        $linked = SongInstrumentPart::query()->where('song_id', $song->id)->where('chart_id', $chart->id)->count();
        $this->assertSame(2, $linked);
    }

    public function test_existing_song_is_matched_by_folder_title(): void
    {
        Storage::fake('local');

        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create([
            'song_code' => '042',
            'name' => 'Already Here',
        ]);
        InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Keys']);

        $this->createFolderChartFixture($this->fixtureRoot, [
            'Already Here' => [
                'Keys.pdf' => $this->minimalPdfContents('keys'),
            ],
        ]);

        Artisan::call('charts:import-commit', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--create-missing-instrument-parts' => true,
        ]);

        $this->assertSame(1, Song::query()->where('band_id', $band->id)->count());
        $this->assertSame('042', $song->fresh()->song_code);
        $this->assertSame(1, Chart::query()->where('song_id', $song->id)->count());
    }

    public function test_import_batch_and_entity_mappings_are_recorded(): void
    {
        Storage::fake('local');

        $band = Band::factory()->create();
        InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Trumpet']);

        $this->createFolderChartFixture($this->fixtureRoot, [
            'Brass Line' => [
                'Trumpet.pdf' => $this->minimalPdfContents('trumpet'),
            ],
        ]);

        Artisan::call('charts:import-commit', [
            '--root' => $this->fixtureRoot,
            '--band' => $band->id,
            '--create-missing-instrument-parts' => true,
        ]);

        $this->assertSame(1, ImportBatch::query()->count());
        $this->assertGreaterThan(0, ImportEntityMapping::query()->count());
    }

    public function test_planner_reports_unknown_instruments(): void
    {
        $band = Band::factory()->create();

        $this->createFolderChartFixture($this->fixtureRoot, [
            'Unknowns' => [
                'Mystery.pdf' => $this->minimalPdfContents('mystery'),
            ],
        ]);

        $report = app(FolderChartImportPlanner::class)->plan(
            new \App\DataTransferObjects\FolderChartImport\FolderChartImportConfig(
                rootPath: $this->fixtureRoot,
                bandId: $band->id,
            ),
            dryRun: true,
        );

        $this->assertCount(1, $report->unmatchedFilenames);
        $this->assertSame('Unknowns/Mystery.pdf', $report->unmatchedFilenames[0]['relative_path']);
    }
}
