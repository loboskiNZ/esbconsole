<?php

namespace Tests\Feature;

use App\Models\AbletonShowFile;
use App\Models\Band;
use App\Models\InstrumentPart;
use App\Models\Musician;
use App\Models\Performance;
use App\Models\PerformanceAssignment;
use App\Models\Readiness;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use App\Models\Soundcheck;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowPerformanceDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_ableton_show_file_reference(): void
    {
        $band = Band::factory()->create();
        $file = AbletonShowFile::factory()->create(['band_id' => $band->id]);

        $show = Show::factory()->create([
            'band_id' => $band->id,
            'ableton_show_file_id' => $file->id,
        ]);

        $this->assertNotNull($show->abletonShowFile);
        $this->assertTrue($show->abletonShowFile->is($file));
    }

    public function test_playlist_items_are_ordered_by_position(): void
    {
        $show = Show::factory()->create();
        $band = $show->band;
        $songA = Song::factory()->forBand($band)->create(['song_code' => '101', 'name' => 'First']);
        $songB = Song::factory()->forBand($band)->create(['song_code' => '102', 'name' => 'Second']);

        ShowPlaylistItem::factory()->create(['show_id' => $show->id, 'song_id' => $songB->id, 'position' => 2, 'ableton_pgm' => 2]);
        ShowPlaylistItem::factory()->create(['show_id' => $show->id, 'song_id' => $songA->id, 'position' => 1, 'ableton_pgm' => 1]);

        $ordered = $show->fresh()->playlistItems->pluck('song.name')->all();

        $this->assertSame(['First', 'Second'], $ordered);
    }

    public function test_duplicate_song_not_allowed_in_same_show_playlist(): void
    {
        $show = Show::factory()->create();
        $song = Song::factory()->forBand($show->band)->create(['song_code' => '103']);

        ShowPlaylistItem::factory()->create(['show_id' => $show->id, 'song_id' => $song->id, 'position' => 1]);

        $this->expectException(QueryException::class);
        ShowPlaylistItem::factory()->create(['show_id' => $show->id, 'song_id' => $song->id, 'position' => 2]);
    }

    public function test_ableton_pgm_is_show_scoped_not_canonical_song_identity(): void
    {
        $show = Show::factory()->create();
        $song = Song::factory()->forBand($show->band)->create(['song_code' => '042']);

        $item = ShowPlaylistItem::factory()->create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => 1,
            'ableton_pgm' => 99,
        ]);

        $this->assertSame('042', $item->song->song_code);
        $this->assertSame(99, $item->ableton_pgm);
    }

    public function test_performance_belongs_to_show(): void
    {
        $show = Show::factory()->create();

        $performance = Performance::factory()->forShow($show)->create([
            'venue' => 'Local Demo Hall',
            'status' => Performance::STATUS_PLANNED,
        ]);

        $this->assertTrue($performance->show->is($show));
        $this->assertTrue($show->fresh()->performances->contains($performance));
    }

    public function test_performance_status_lifecycle_values(): void
    {
        $performance = Performance::factory()->create(['status' => Performance::STATUS_PLANNED]);

        $performance->update(['status' => Performance::STATUS_PREPARING]);
        $this->assertSame(Performance::STATUS_PREPARING, $performance->fresh()->status);

        $performance->update(['status' => Performance::STATUS_SOUNDCHECK]);
        $performance->update(['status' => Performance::STATUS_READY]);
        $performance->update(['status' => Performance::STATUS_LIVE]);
        $performance->update(['status' => Performance::STATUS_COMPLETED]);
        $performance->update(['status' => Performance::STATUS_ARCHIVED]);

        $this->assertSame(Performance::STATUS_ARCHIVED, $performance->fresh()->status);
    }

    public function test_performance_assignment_relationships(): void
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $performance = Performance::factory()->forShow($show)->create();
        $musician = Musician::factory()->create(['band_id' => $band->id]);
        $part = InstrumentPart::factory()->create(['band_id' => $band->id]);

        $assignment = PerformanceAssignment::factory()->create([
            'performance_id' => $performance->id,
            'musician_id' => $musician->id,
            'instrument_part_id' => $part->id,
        ]);

        $this->assertTrue($assignment->performance->is($performance));
        $this->assertTrue($assignment->musician->is($musician));
        $this->assertTrue($assignment->instrumentPart->is($part));
    }

    public function test_soundcheck_and_readiness_foundation(): void
    {
        $performance = Performance::factory()->create();

        $soundcheck = Soundcheck::factory()->create([
            'performance_id' => $performance->id,
            'status' => Soundcheck::STATUS_NOT_STARTED,
        ]);

        $readiness = Readiness::factory()->create([
            'performance_id' => $performance->id,
            'status' => Readiness::STATUS_WARNING,
        ]);

        $this->assertTrue($performance->fresh()->soundcheck->is($soundcheck));
        $this->assertTrue($performance->fresh()->readiness->is($readiness));
        $this->assertSame(Readiness::STATUS_WARNING, $readiness->status);
    }

    public function test_readiness_warning_does_not_imply_blocked_status(): void
    {
        $performance = Performance::factory()->create(['status' => Performance::STATUS_READY]);

        Readiness::factory()->create([
            'performance_id' => $performance->id,
            'status' => Readiness::STATUS_WARNING,
        ]);

        $this->assertSame(Performance::STATUS_READY, $performance->fresh()->status);
    }
}
