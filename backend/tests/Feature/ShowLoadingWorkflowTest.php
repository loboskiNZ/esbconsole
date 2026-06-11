<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Chart;
use App\Models\Cue;
use App\Models\InstrumentPart;
use App\Models\Musician;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ShowLoadingWorkflowTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_director_can_complete_manual_show_loading_workflow(): void
    {
        Storage::fake('local');

        $user = $this->createDirectorUser();
        $band = Band::factory()->create(['name' => 'Thieves Alley Band']);

        $this->actingAs($user);

        $this->post(route('shows.store'), [
            'name' => 'Thieves Alley',
            'description' => 'Manual load test show',
        ])->assertRedirect();

        $show = Show::query()->where('name', 'Thieves Alley')->firstOrFail();
        $this->assertSame($band->id, $show->band_id);

        $this->get(route('shows.show', $show))->assertOk()->assertSee('Thieves Alley');

        $this->post(route('songs.store'), ['name' => 'Opening Number'])->assertRedirect();
        $this->post(route('songs.store'), ['name' => 'Finale'])->assertRedirect();

        $songA = Song::query()->where('name', 'Opening Number')->firstOrFail();
        $songB = Song::query()->where('name', 'Finale')->firstOrFail();

        $this->post(route('playlist.store', $show), ['song_id' => $songA->id])->assertRedirect();
        $this->post(route('playlist.store', $show), ['song_id' => $songB->id])->assertRedirect();

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame($songA->id, $items[0]->song_id);

        $this->post(route('playlist.reorder', $show), [
            'order' => [$items[1]->id, $items[0]->id],
        ])->assertRedirect();

        $reordered = $show->fresh()->playlistItems()->orderBy('position')->pluck('song_id')->all();
        $this->assertSame([$songB->id, $songA->id], $reordered);

        $this->post(route('songs.cues.store', $songA), [
            'cue_number' => '000',
            'name' => 'Preparation',
        ])->assertRedirect();

        $this->post(route('songs.cues.store', $songA), [
            'cue_number' => '010',
            'name' => 'Verse',
        ])->assertRedirect();

        $this->assertSame(2, $songA->fresh()->cues()->count());

        $this->post(route('instrument-parts.store'), ['name' => 'Lead Vocal'])->assertRedirect();
        $this->post(route('instrument-parts.store'), ['name' => 'Guitar'])->assertRedirect();

        $leadVocal = InstrumentPart::query()->where('name', 'Lead Vocal')->firstOrFail();
        $guitar = InstrumentPart::query()->where('name', 'Guitar')->firstOrFail();

        $this->post(route('songs.instrument-parts.store', $songA), [
            'instrument_part_id' => $leadVocal->id,
        ])->assertRedirect();

        $this->post(route('songs.instrument-parts.store', $songA), [
            'instrument_part_id' => $guitar->id,
        ])->assertRedirect();

        $sips = $songA->fresh()->songInstrumentParts()->get();
        $this->assertCount(2, $sips);

        $file = UploadedFile::fake()->create('opening-chart.pdf', 100, 'application/pdf');

        $this->post(route('songs.charts.store', $songA), [
            'title' => 'Opening Chart',
            'chart' => $file,
        ])->assertRedirect();

        $chart = Chart::query()->where('title', 'Opening Chart')->firstOrFail();
        Storage::disk('local')->assertExists($chart->storage_reference);

        $this->post(route('charts.assign', $chart), [
            'song_instrument_part_ids' => $sips->pluck('id')->all(),
        ])->assertRedirect();

        foreach ($sips as $sip) {
            $this->assertSame($chart->id, $sip->fresh()->chart_id);
        }

        $this->post(route('musicians.store'), [
            'first_name' => 'Ed',
            'last_name' => 'Operator',
        ])->assertRedirect();

        $this->assertDatabaseHas('musicians', [
            'band_id' => $band->id,
            'first_name' => 'Ed',
            'last_name' => 'Operator',
        ]);

        $this->get(route('shows.show', $show))
            ->assertOk()
            ->assertSee('Finale')
            ->assertSee('Opening Number');

        $this->get(route('songs.show', $songA))
            ->assertOk()
            ->assertSee('000')
            ->assertSee('Preparation')
            ->assertSee('Opening Chart')
            ->assertSee('Lead Vocal')
            ->assertSee('Guitar');

        $this->get(route('musicians.index'))->assertOk()->assertSee('Ed Operator');
    }

    public function test_show_loading_routes_require_director_role(): void
    {
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);
        $song = Song::factory()->forBand($band)->create();

        $this->get(route('shows.create'))->assertRedirect(route('login'));

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get(route('songs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('shows.show', $show))->assertForbidden();
        $this->actingAs($user)->get(route('songs.show', $song))->assertForbidden();
    }

    public function test_band_scoping_blocks_cross_band_access(): void
    {
        $user = $this->createDirectorUser();
        $bandA = Band::factory()->create(['name' => 'Band A']);
        Band::factory()->create(['name' => 'Band B']);

        $otherShow = Show::factory()->create(['band_id' => Band::query()->where('name', 'Band B')->value('id')]);

        $this->actingAs($user)
            ->get(route('shows.show', $otherShow))
            ->assertNotFound();
    }
}
