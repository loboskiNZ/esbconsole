<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Chart;
use App\Models\InstrumentPart;
use App\Models\MusicalKey;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use App\Models\SongMood;
use App\Models\TimeSignature;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class SongAuthoringWorkspaceTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SongMetadataReferenceSeeder::class);
    }

    public function test_director_can_access_song_authoring_workspace(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['name' => 'Workspace Song']);

        $this->actingAs($user)->get(route('songs.show', $song))
            ->assertOk()
            ->assertSee('Song Authoring Workspace', false)
            ->assertSee('Overview', false)
            ->assertSee('Musical Metadata', false)
            ->assertSee('Director Brief', false)
            ->assertSee('Charts / Instrument Parts', false)
            ->assertSee('References', false)
            ->assertSee('Sync Readiness', false)
            ->assertSee('Workspace Song', false)
            ->assertSee($song->song_code, false);
    }

    public function test_director_can_update_musical_metadata_and_brief(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['name' => 'Authoring Song']);

        $timeSignature = TimeSignature::query()->where('label', '4/4')->firstOrFail();
        $musicalKey = MusicalKey::query()->where('label', 'A minor')->firstOrFail();
        $mood = SongMood::query()->where('slug', 'romantic')->firstOrFail();

        $this->actingAs($user)->put(route('songs.update', $song), [
            'name' => 'Authoring Song',
            'bpm' => 84,
            'time_signature_id' => $timeSignature->id,
            'musical_key_id' => $musicalKey->id,
            'mood_id' => $mood->id,
            'genre' => 'Ballad',
            'style' => 'Slow rock',
            'tempo_feel' => 'Laid back',
            'count_in' => 2,
            'director_notes' => 'Let the verse breathe.',
            'mood_intention' => 'Warm and intimate.',
            'performance_feel' => 'Soft dynamics in verse.',
            'arrangement_comments' => 'Extended outro.',
            'reference_url' => 'https://example.com/reference',
            'reference_title' => 'Original recording',
            'reference_notes' => 'Listen from 1:12.',
            'status' => Song::STATUS_IN_PROGRESS,
        ])->assertRedirect(route('songs.show', $song));

        $song->refresh();

        $this->assertSame(84, $song->bpm);
        $this->assertSame('Ballad', $song->genre);
        $this->assertSame(2, $song->count_in);
        $this->assertSame('Let the verse breathe.', $song->director_notes);
        $this->assertSame('Warm and intimate.', $song->mood_intention);
        $this->assertSame('https://example.com/reference', $song->reference_url);
    }

    public function test_director_workspace_shows_charts_and_instrument_parts(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $part = InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Trumpet']);
        $song = Song::factory()->forBand($band)->create(['name' => 'Charted Song']);
        $chart = Chart::factory()->create([
            'song_id' => $song->id,
            'title' => 'Trumpet Chart',
            'original_filename' => 'trumpet-lead.pdf',
            'storage_reference' => 'charts/99/trumpet-lead.pdf',
        ]);
        SongInstrumentPart::factory()->create([
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
            'chart_id' => $chart->id,
        ]);

        $this->actingAs($user)->get(route('songs.show', $song))
            ->assertOk()
            ->assertSee('Trumpet', false)
            ->assertSee('Trumpet Chart', false)
            ->assertSee('trumpet-lead.pdf', false)
            ->assertSee('charts/99/trumpet-lead.pdf', false)
            ->assertSee('Linked', false);
    }

    public function test_director_workspace_shows_missing_chart_state(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $part = InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Alto Sax']);
        $song = Song::factory()->forBand($band)->create();
        SongInstrumentPart::factory()->create([
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
            'chart_id' => null,
        ]);

        $this->actingAs($user)->get(route('songs.show', $song))
            ->assertOk()
            ->assertSee('Missing chart', false)
            ->assertSee('1 part(s) without a chart', false);
    }

    public function test_invalid_bpm_and_foreign_keys_rejected(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create();

        $this->actingAs($user)->put(route('songs.update', $song), [
            'name' => $song->name,
            'bpm' => 400,
            'time_signature_id' => 99999,
            'status' => Song::STATUS_DRAFT,
        ])->assertSessionHasErrors(['bpm', 'time_signature_id']);
    }

    public function test_non_director_cannot_access_song_authoring(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('musician'));

        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create();

        $this->actingAs($user)->get(route('songs.show', $song))->assertForbidden();
        $this->actingAs($user)->put(route('songs.update', $song), [
            'name' => 'Blocked',
            'status' => Song::STATUS_DRAFT,
        ])->assertForbidden();
    }

    public function test_workspace_has_no_checkout_or_sync_controls(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create();

        $this->actingAs($user)->get(route('songs.show', $song))
            ->assertOk()
            ->assertSee('not implemented in this phase', false)
            ->assertDontSee('Check out', false)
            ->assertDontSee('Synchronise', false)
            ->assertDontSee('last-write-wins', false)
            ->assertDontSee('Readiness score', false)
            ->assertDontSee('completion', false)
            ->assertDontSee('evaluation', false);
    }

    public function test_authoring_fields_exist_on_songs_table(): void
    {
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create([
            'genre' => 'Funk',
            'mood_intention' => 'Tight groove',
        ]);

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'genre' => 'Funk',
            'mood_intention' => 'Tight groove',
        ]);
    }
}
