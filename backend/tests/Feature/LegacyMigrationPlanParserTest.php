<?php

namespace Tests\Feature;

use App\DataTransferObjects\LegacyImport\LegacyImportConfig;
use App\Models\Chart;
use App\Models\Cue;
use App\Models\ImportBatch;
use App\Models\InstrumentPart;
use App\Models\Show;
use App\Models\Snippet;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use App\Services\LegacyImport\LegacyMigrationPlanService;
use App\Services\LegacyImport\LegacyRoleNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyMigrationPlanParserTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = base_path('tests/fixtures/legacy/minimal');
    }

    private function config(): LegacyImportConfig
    {
        return new LegacyImportConfig(
            projectRoot: $this->fixtureRoot,
            bandSlug: 'fixture-band',
        );
    }

    public function test_import_batch_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('import_batches'));
        $this->assertTrue(Schema::hasTable('import_entity_mappings'));
    }

    public function test_parser_reads_minimal_legacy_setlists_fixture(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $this->assertSame('Fixture Show', $plan->show->name);
        $this->assertCount(3, $plan->songs);
        $this->assertCount(3, $plan->playlistItems);
    }

    public function test_synthetic_cue_000_is_added_for_each_song(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $preparationCues = array_filter(
            $plan->cues,
            fn ($cue) => $cue->syntheticPreparation === true,
        );

        $this->assertCount(3, $preparationCues);

        foreach ($preparationCues as $cue) {
            $this->assertSame('000', $cue->cueNumber);
            $this->assertSame(0, $cue->sequenceOrder);
            $this->assertSame('Preparation', $cue->name);
        }
    }

    public function test_legacy_cue_index_maps_to_cue_number_correctly(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $songOneCues = array_values(array_filter(
            $plan->cues,
            fn ($cue) => $cue->legacySongId === '100001' && ! $cue->syntheticPreparation,
        ));

        $this->assertSame('001', $songOneCues[0]->cueNumber);
        $this->assertSame(0, $songOneCues[0]->legacyCueIndex);
        $this->assertSame(1, $songOneCues[0]->sequenceOrder);
        $this->assertSame('002', $songOneCues[1]->cueNumber);
        $this->assertSame(1, $songOneCues[1]->legacyCueIndex);
        $this->assertSame(2, $songOneCues[1]->sequenceOrder);
    }

    public function test_song_code_assignment_is_sequential_from_playlist_order(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $this->assertSame('001', $plan->songs[0]->songCode);
        $this->assertSame('100001', $plan->songs[0]->legacySongId);
        $this->assertSame('002', $plan->songs[1]->songCode);
        $this->assertSame('100002', $plan->songs[1]->legacySongId);
        $this->assertSame(1, $plan->playlistItems[0]->abletonPgm);
        $this->assertSame(2, $plan->playlistItems[1]->abletonPgm);
    }

    public function test_legacy_ids_remain_metadata_only(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        foreach ($plan->songs as $song) {
            $this->assertNotSame($song->legacySongId, $song->songCode);
            $this->assertMatchesRegularExpression('/^\d{3}$/', $song->songCode);
        }

        $this->assertArrayHasKey('100001', $plan->legacyIdMappings['songs']);
        $this->assertSame('001', $plan->legacyIdMappings['songs']['100001']['song_code']);
    }

    public function test_chart_candidates_are_generated(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $this->assertNotEmpty($plan->charts);

        $songOneTrumpet = array_values(array_filter(
            $plan->charts,
            fn ($chart) => $chart->legacySongId === '100001'
                && in_array('trumpet', $chart->assignedRoleSlugs, true),
        ));

        $this->assertNotEmpty($songOneTrumpet);
        $this->assertTrue($songOneTrumpet[0]->fileExists);
        $this->assertNotNull($songOneTrumpet[0]->checksum);
        $this->assertStringStartsWith('migrated/charts/fixture-band/', $songOneTrumpet[0]->expectedStorageReference);
    }

    public function test_shared_chart_candidate_detection_deduplicates_by_checksum(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $songTwoCharts = array_values(array_filter(
            $plan->charts,
            fn ($chart) => $chart->legacySongId === '100002',
        ));

        $sharedCharts = array_filter(
            $songTwoCharts,
            fn ($chart) => count($chart->assignedRoleSlugs) >= 2,
        );

        $this->assertCount(1, $sharedCharts);

        $shared = array_values($sharedCharts)[0];
        $this->assertContains('machines', $shared->assignedRoleSlugs);
        $this->assertContains('singer', $shared->assignedRoleSlugs);
    }

    public function test_snippet_candidates_generated_from_visual_snippets(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $this->assertNotEmpty($plan->snippets);

        $introTrumpet = array_values(array_filter(
            $plan->snippets,
            fn ($snippet) => $snippet->legacySongId === '100001'
                && $snippet->legacyCueIndex === 0
                && $snippet->legacyRoleSlug === 'trumpet',
        ));

        $this->assertCount(1, $introTrumpet);
        $this->assertSame('001', $introTrumpet[0]->cueNumber);
        $this->assertSame('chart_crop', $introTrumpet[0]->sourceType);
        $this->assertTrue($introTrumpet[0]->fileExists);
        $this->assertNotNull($introTrumpet[0]->chartCandidateKey);
    }

    public function test_missing_assets_are_reported_not_ignored(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $this->assertNotEmpty($plan->issues->missingSnippetFiles);

        $missingTrumpet = array_values(array_filter(
            $plan->issues->missingSnippetFiles,
            fn ($asset) => $asset->legacySongId === '100002'
                && $asset->roleSlug === 'trumpet',
        ));

        $this->assertCount(1, $missingTrumpet);
        $this->assertSame(1, $missingTrumpet[0]->legacyCueIndex);
    }

    public function test_parser_does_not_write_canonical_tables(): void
    {
        $this->assertSame(0, Song::query()->count());
        $this->assertSame(0, Show::query()->count());
        $this->assertSame(0, Cue::query()->count());
        $this->assertSame(0, Chart::query()->count());
        $this->assertSame(0, Snippet::query()->count());
        $this->assertSame(0, SongInstrumentPart::query()->count());
        $this->assertSame(0, InstrumentPart::query()->count());
        $this->assertSame(0, ImportBatch::query()->count());

        app(LegacyMigrationPlanService::class)->buildPlan($this->config());

        $this->assertSame(0, Song::query()->count());
        $this->assertSame(0, Show::query()->count());
        $this->assertSame(0, Cue::query()->count());
        $this->assertSame(0, Chart::query()->count());
        $this->assertSame(0, Snippet::query()->count());
        $this->assertSame(0, SongInstrumentPart::query()->count());
        $this->assertSame(0, InstrumentPart::query()->count());
        $this->assertSame(0, ImportBatch::query()->count());
    }

    public function test_role_normalizer_handles_guitarrist_typo(): void
    {
        $normalizer = app(LegacyRoleNormalizer::class);

        $this->assertSame('Guitar', $normalizer->normalize('Guitarrist'));
    }

    public function test_manifest_array_is_serializable(): void
    {
        $plan = app(LegacyMigrationPlanService::class)->buildPlan($this->config());
        $manifest = $plan->toManifestArray();

        $this->assertArrayHasKey('import_batch_id', $manifest);
        $this->assertArrayHasKey('legacy_id_mappings', $manifest);
        $this->assertArrayHasKey('issues', $manifest);
    }
}
