<?php

namespace Tests\Feature;

use App\Models\Chart;
use App\Models\Cue;
use App\Models\Snippet;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use App\Services\Snippet\ChartSnippetFreshnessService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnippetDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_asset_can_be_shared_across_multiple_song_instrument_parts(): void
    {
        $song = Song::factory()->create(['song_code' => '101']);
        $chart = Chart::factory()->create(['song_id' => $song->id]);

        $sipA = SongInstrumentPart::factory()->create(['song_id' => $song->id, 'chart_id' => $chart->id]);
        $sipB = SongInstrumentPart::factory()->create(['song_id' => $song->id, 'chart_id' => $chart->id]);

        $this->assertTrue($sipA->chart->is($chart));
        $this->assertTrue($sipB->chart->is($chart));
        $this->assertCount(2, $chart->fresh()->songInstrumentParts);
        $this->assertSame($chart->storage_reference, $sipA->chart->storage_reference);
        $this->assertSame($chart->storage_reference, $sipB->chart->storage_reference);
    }

    public function test_each_song_instrument_part_has_at_most_one_chart_assignment(): void
    {
        $song = Song::factory()->create(['song_code' => '102']);
        $chart = Chart::factory()->create(['song_id' => $song->id]);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id, 'chart_id' => $chart->id]);

        $this->assertSame($chart->id, $sip->chart_id);
        $this->assertTrue($sip->chart->is($chart));
    }

    public function test_one_active_snippet_per_song_instrument_part_and_cue(): void
    {
        $song = Song::factory()->create(['song_code' => '103']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '002']);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id]);

        Snippet::factory()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        Snippet::factory()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
            'is_active' => true,
        ]);
    }

    public function test_inactive_historical_snippets_allowed_for_same_song_instrument_part_and_cue(): void
    {
        $song = Song::factory()->create(['song_code' => '104']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003']);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id]);

        Snippet::factory()->inactive()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
        ]);

        $active = Snippet::factory()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
            'is_active' => true,
        ]);

        $this->assertTrue($active->is_active);
        $this->assertCount(2, Snippet::query()->where('song_instrument_part_id', $sip->id)->where('cue_id', $cue->id)->get());
    }

    public function test_cloned_snippet_persists_independent_copy_metadata(): void
    {
        $song = Song::factory()->create(['song_code' => '105']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '004']);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id]);

        $source = Snippet::factory()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
            'source_type' => Snippet::SOURCE_CHART_CROP,
            'storage_reference' => 'local-demo/snippets/source.png',
            'checksum' => 'source-checksum',
        ]);

        $targetCue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '005']);

        $clone = Snippet::factory()->clonedFrom($source)->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $targetCue->id,
        ]);

        $this->assertSame(Snippet::SOURCE_CLONE, $clone->source_type);
        $this->assertTrue($clone->sourceSnippet->is($source));
        $this->assertNotSame($source->storage_reference, $clone->storage_reference);
        $this->assertNotSame($source->checksum, $clone->checksum);
    }

    public function test_snippet_source_type_is_persisted(): void
    {
        $snippet = Snippet::factory()->create(['source_type' => Snippet::SOURCE_PHOTO]);

        $this->assertSame(Snippet::SOURCE_PHOTO, $snippet->fresh()->source_type);
    }

    public function test_snippet_freshness_state_is_persisted(): void
    {
        $snippet = Snippet::factory()->create(['freshness_state' => Snippet::FRESHNESS_NEEDS_REVIEW]);

        $this->assertSame(Snippet::FRESHNESS_NEEDS_REVIEW, $snippet->fresh()->freshness_state);
    }

    public function test_chart_crop_metadata_is_persisted(): void
    {
        $song = Song::factory()->create(['song_code' => '106']);
        $chart = Chart::factory()->create(['song_id' => $song->id, 'checksum' => 'chart-v1']);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id, 'chart_id' => $chart->id]);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '006']);

        $metadata = ['page' => 2, 'x' => 15, 'y' => 25, 'width' => 500, 'height' => 350];

        $snippet = Snippet::factory()->chartCrop($chart, $metadata)->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
        ]);

        $this->assertSame($chart->id, $snippet->source_chart_id);
        $this->assertSame('chart-v1', $snippet->chart_revision_at_creation);
        $this->assertSame($metadata, $snippet->source_metadata);
    }

    public function test_chart_update_marks_related_snippets_out_of_date_without_deleting_them(): void
    {
        $song = Song::factory()->create(['song_code' => '107']);
        $chart = Chart::factory()->create(['song_id' => $song->id, 'checksum' => 'original-checksum']);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id, 'chart_id' => $chart->id]);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '007']);

        $snippet = Snippet::factory()->chartCrop($chart)->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
        ]);

        $chart->update(['checksum' => 'updated-checksum']);

        $affected = app(ChartSnippetFreshnessService::class)->markAffectedSnippetsOutOfDate($chart->fresh());

        $this->assertSame(1, $affected);
        $this->assertSame(Snippet::FRESHNESS_OUT_OF_DATE, $snippet->fresh()->freshness_state);
        $this->assertDatabaseHas('snippets', ['id' => $snippet->id, 'storage_reference' => $snippet->storage_reference]);
    }

    public function test_cue_sequence_order_can_change_without_changing_cue_number_identity(): void
    {
        $song = Song::factory()->create(['song_code' => '108']);

        $intro = Cue::factory()->create([
            'song_id' => $song->id,
            'cue_number' => '001',
            'sequence_order' => 2,
            'name' => 'Intro',
        ]);

        $verse = Cue::factory()->create([
            'song_id' => $song->id,
            'cue_number' => '002',
            'sequence_order' => 1,
            'name' => 'Verse',
        ]);

        $ordered = Cue::query()->where('song_id', $song->id)->inPerformanceOrder()->pluck('cue_number')->all();

        $this->assertSame(['002', '001'], $ordered);
        $this->assertSame('001', $intro->fresh()->cue_number);
        $this->assertSame('002', $verse->fresh()->cue_number);
        $this->assertSame('108.001', $intro->fresh()->runtimeIdentity());
        $this->assertSame('108.002', $verse->fresh()->runtimeIdentity());
    }
}
