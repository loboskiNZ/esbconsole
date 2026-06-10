<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Band;
use App\Models\Capability;
use App\Models\Chart;
use App\Models\Cue;
use App\Models\InstrumentPart;
use App\Models\Musician;
use App\Models\Snippet;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_song_code_is_unique_per_band(): void
    {
        $band = Band::factory()->create();

        Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Song A']);
        Song::factory()->forBand($band)->create(['song_code' => '002', 'name' => 'Song B']);

        $otherBand = Band::factory()->create();
        Song::factory()->forBand($otherBand)->create(['song_code' => '001', 'name' => 'Other Song']);

        $this->expectException(QueryException::class);
        Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Duplicate']);
    }

    public function test_cue_number_is_unique_within_song(): void
    {
        $song = Song::factory()->create(['song_code' => '010']);

        Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '001', 'name' => 'Intro']);

        $this->expectException(QueryException::class);
        Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '001', 'name' => 'Duplicate']);
    }

    public function test_cue_000_preparation_is_allowed(): void
    {
        $song = Song::factory()->create(['song_code' => '011']);

        $cue = Cue::factory()->preparation()->create(['song_id' => $song->id]);

        $this->assertSame('000', $cue->cue_number);
        $this->assertSame('011.000', $cue->runtimeIdentity());
    }

    public function test_song_instrument_part_relationships(): void
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['song_code' => '012']);
        $part = InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Lead Vocal']);

        $sip = SongInstrumentPart::factory()->create([
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
        ]);

        $this->assertTrue($sip->song->is($song));
        $this->assertTrue($sip->instrumentPart->is($part));
        $this->assertTrue($song->fresh()->songInstrumentParts->contains($sip));
    }

    public function test_chart_owned_by_song_instrument_part(): void
    {
        $sip = SongInstrumentPart::factory()->create();
        $chart = Chart::factory()->create(['song_instrument_part_id' => $sip->id]);

        $this->assertTrue($chart->songInstrumentPart->is($sip));
        $this->assertTrue($sip->fresh()->charts->contains($chart));
    }

    public function test_snippet_owned_by_song_instrument_part_and_cue(): void
    {
        $song = Song::factory()->create(['song_code' => '013']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '002', 'name' => 'Verse']);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id]);

        $snippet = Snippet::factory()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
        ]);

        $this->assertTrue($snippet->songInstrumentPart->is($sip));
        $this->assertTrue($snippet->cue->is($cue));
        $this->assertTrue($sip->fresh()->snippets->contains($snippet));
    }

    public function test_snippet_uniqueness_per_song_instrument_part_and_cue(): void
    {
        $song = Song::factory()->create(['song_code' => '014']);
        $cue = Cue::factory()->create(['song_id' => $song->id, 'cue_number' => '003', 'name' => 'Chorus']);
        $sip = SongInstrumentPart::factory()->create(['song_id' => $song->id]);

        Snippet::factory()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
        ]);

        $this->expectException(QueryException::class);
        Snippet::factory()->create([
            'song_instrument_part_id' => $sip->id,
            'cue_id' => $cue->id,
        ]);
    }

    public function test_assignment_relationships(): void
    {
        $band = Band::factory()->create();
        $musician = Musician::factory()->create(['band_id' => $band->id]);
        $part = InstrumentPart::factory()->create(['band_id' => $band->id]);

        $assignment = Assignment::factory()->create([
            'musician_id' => $musician->id,
            'instrument_part_id' => $part->id,
        ]);

        $this->assertTrue($assignment->musician->is($musician));
        $this->assertTrue($assignment->instrumentPart->is($part));
        $this->assertTrue($musician->fresh()->assignments->contains($assignment));
    }

    public function test_musician_capability_relationships(): void
    {
        $band = Band::factory()->create();
        $musician = Musician::factory()->create(['band_id' => $band->id]);
        $part = InstrumentPart::factory()->create(['band_id' => $band->id]);

        $capability = Capability::factory()->create([
            'musician_id' => $musician->id,
            'instrument_part_id' => $part->id,
        ]);

        $this->assertTrue($capability->musician->is($musician));
        $this->assertTrue($capability->instrumentPart->is($part));
        $this->assertTrue($musician->fresh()->capabilities->contains($capability));
    }

    public function test_models_load_with_expected_fillable_identity_fields(): void
    {
        $song = Song::factory()->create([
            'song_code' => '015',
            'status' => Song::STATUS_IN_PROGRESS,
        ]);

        $this->assertSame('015', $song->song_code);
        $this->assertSame(Song::STATUS_IN_PROGRESS, $song->status);
        $this->assertNotEmpty($song->public_id);
    }
}
