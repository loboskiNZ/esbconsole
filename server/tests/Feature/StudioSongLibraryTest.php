<?php

namespace Tests\Feature;

use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\MusicalKey;
use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Models\Library\SongInstrumentPart;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\User;
use App\Services\StudioShowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioSongLibraryTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal.band_id' => 1,
            'portal.library_connection' => 'sqlite',
        ]);
        $this->ensurePortalBand();
        $this->seedReferenceTables();
    }

    public function test_director_sees_music_library_entry_points(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get(route('studio'))
            ->assertOk()
            ->assertSee('Music Library', false)
            ->assertSee('Manage Songs', false)
            ->assertSee('esb-studio__music-library-dashboard', false);

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertSee('+ New Song', false)
            ->assertSee('Music Library', false)
            ->assertSee('esb-studio__song-library-panel', false);
    }

    public function test_music_library_parent_card_and_song_cards_render(): void
    {
        $director = $this->createDirectorUser();
        $this->seedSong('Catalogue Song', '020');

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertSee('esb-studio__song-library-panel', false)
            ->assertSee('esb-studio__song-library-summary-grid', false)
            ->assertSee('esb-studio__song-library-item-card', false)
            ->assertSee('esb-studio__song-library-title">Catalogue Song', false)
            ->assertSee('esb-studio__song-status-pill', false)
            ->assertSee('Ready', false);
    }

    public function test_dashboard_music_library_summary_renders_from_existing_data(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSongWithRelationships('Dashboard Summary Song', '021');

        $this->actingAs($director)->get(route('studio'))
            ->assertOk()
            ->assertSee('esb-studio__music-library-dashboard-stats', false)
            ->assertSee('Manage Songs →', false);

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertSee('>Songs</dt>', false)
            ->assertSee('>1</dd>', false)
            ->assertSee('>Charts</dt>', false);
    }

    public function test_library_search_matches_spotify_and_youtube_urls(): void
    {
        $director = $this->createDirectorUser();

        Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '030',
            'name' => 'Streaming Reference',
            'spotify_url' => 'https://open.spotify.com/track/abc123unique',
            'youtube_url' => 'https://www.youtube.com/watch?v=xyz789unique',
            'status' => Song::STATUS_READY,
        ]);

        $this->actingAs($director)->get(route('songs.index', ['q' => 'abc123unique']))
            ->assertOk()
            ->assertSee('esb-studio__song-library-title">Streaming Reference', false);

        $this->actingAs($director)->get(route('songs.index', ['q' => 'xyz789unique']))
            ->assertOk()
            ->assertSee('esb-studio__song-library-title">Streaming Reference', false);
    }

    public function test_expanded_song_card_shows_show_usage_when_on_playlist(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Summer Tour']);
        $song = $this->seedSong('Playlist Member', '031');
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertSee('Used in', false)
            ->assertSee('Summer Tour', false)
            ->assertSee('Last played', false)
            ->assertSee('Not available', false);
    }

    public function test_musician_cannot_see_song_library_entry_points(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)->get(route('studio'))
            ->assertOk()
            ->assertDontSee('Manage Songs', false)
            ->assertDontSee('Music Library', false);

        $this->actingAs($musician)->get(route('songs.index'))->assertForbidden();
        $this->actingAs($musician)->get(route('songs.create'))->assertForbidden();
    }

    public function test_director_can_create_song_and_is_redirected_to_edit(): void
    {
        $director = $this->createDirectorUser();
        $keyId = MusicalKey::query()->where('label', 'G major')->value('id');

        $this->actingAs($director)->get(route('songs.create'));

        $response = $this->actingAs($director)->post(route('songs.store'), [
            '_token' => session()->token(),
            'name' => 'Library Created Song',
            'bpm' => 128,
            'musical_key_id' => $keyId,
            'director_notes' => 'Start with the intro vamp.',
            'spotify_url' => 'https://open.spotify.com/track/example',
        ]);

        $song = Song::query()->where('name', 'Library Created Song')->first();
        $this->assertNotNull($song);
        $this->assertSame('draft', $song->status);
        $this->assertSame('001', $song->song_code);

        $response
            ->assertRedirect(route('songs.edit', $song))
            ->assertSessionHas('song_created', true);

        $this->actingAs($director)->get(route('songs.edit', $song))
            ->assertOk()
            ->assertSee('Song created. Continue by adding instrument parts, charts and song assets.', false);

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertSee('esb-studio__song-library-title">Library Created Song', false);
    }

    public function test_created_song_appears_in_playlist_picker(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Picker Show']);

        $this->actingAs($director)->get(route('songs.create'));

        $this->actingAs($director)->post(route('songs.store'), [
            '_token' => session()->token(),
            'name' => 'Picker Visible Song',
        ]);

        $this->actingAs($director)->getJson(route('studio.shows.playlist.songs.search', [
            'show' => $show,
            'q' => 'Picker Visible',
        ]))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Picker Visible Song');
    }

    public function test_director_can_archive_song_and_musician_cannot(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $song = $this->seedSong('Archive Candidate', '010');

        $this->actingAs($director)->get(route('songs.index'));

        $this->actingAs($director)
            ->patch(route('songs.archive', $song), ['_token' => session()->token()])
            ->assertRedirect(route('songs.index'))
            ->assertSessionHas('song_archived', 'Archive Candidate');

        $this->assertSame(Song::STATUS_ARCHIVED, $song->fresh()->status);

        $this->actingAs($musician)->get(route('songs.index'));

        $this->actingAs($musician)
            ->patch(route('songs.archive', $song), ['_token' => session()->token()])
            ->assertForbidden();
    }

    public function test_archived_song_is_hidden_from_active_library_and_playlist_picker(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Archive Filter Show']);
        $song = $this->seedSong('Hidden When Archived', '011');

        $this->actingAs($director)->get(route('songs.index'));

        $this->actingAs($director)->patch(route('songs.archive', $song), ['_token' => session()->token()]);

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertDontSee('esb-studio__song-library-title">Hidden When Archived', false);

        $this->actingAs($director)->getJson(route('studio.shows.playlist.songs.search', [
            'show' => $show,
            'q' => 'Hidden When Archived',
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $this->actingAs($director)->get(route('songs.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('esb-studio__song-library-title">Hidden When Archived', false);
    }

    public function test_archived_song_remains_on_existing_playlist_with_relationships_preserved(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Existing Playlist Show']);
        $song = $this->seedSongWithRelationships('Still On Playlist', '012');

        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('songs.index'));

        $this->actingAs($director)->patch(route('songs.archive', $song), ['_token' => session()->token()]);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Still On Playlist', false);

        $this->assertDatabaseHas('show_playlist_items', [
            'show_id' => $show->id,
            'song_id' => $song->id,
            'is_active' => true,
        ]);
        $this->assertSame(1, Chart::query()->where('song_id', $song->id)->count());
        $this->assertSame(1, SongAsset::query()->where('song_id', $song->id)->count());
        $this->assertSame(1, SongInstrumentPart::query()->where('song_id', $song->id)->count());
    }

    public function test_director_can_restore_archived_song(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Restore Me', '013', Song::STATUS_ARCHIVED);

        $this->actingAs($director)->get(route('songs.index', ['archived' => 1]));

        $this->actingAs($director)
            ->patch(route('songs.restore', $song), ['_token' => session()->token()])
            ->assertRedirect(route('songs.index', ['archived' => 1]))
            ->assertSessionHas('song_restored', 'Restore Me');

        $this->assertSame(Song::STATUS_DRAFT, $song->fresh()->status);

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertSee('esb-studio__song-library-title">Restore Me', false);
    }

    public function test_adding_archived_song_to_playlist_is_rejected(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Reject Archived Show']);
        $song = $this->seedSong('Archived Add Block', '014', Song::STATUS_ARCHIVED);

        $this->actingAs($director)->get(route('studio.shows.show', $show));

        $this->actingAs($director)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->postJson(route('studio.shows.playlist.items.store', $show), [
                'song_id' => $song->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Song is archived and cannot be added to a playlist.');
    }

    private function seedShow(array $attributes = []): Show
    {
        return app(StudioShowService::class)->createShow(array_merge([
            'name' => 'Seed Show',
            'lifecycle_state' => Show::STATE_DRAFT,
        ], $attributes));
    }

    private function seedPlaylistItem(Show $show, Song $song): ShowPlaylistItem
    {
        return ShowPlaylistItem::query()->create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => 1,
            'is_active' => true,
        ]);
    }

    private function seedSong(string $name, string $songCode = '001', string $status = Song::STATUS_READY): Song
    {
        return Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => $songCode,
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function seedSongWithRelationships(string $name, string $songCode): Song
    {
        $song = $this->seedSong($name, $songCode);

        $part = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Bass',
            'active' => true,
        ]);

        $chart = Chart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'title' => 'Bass Chart',
            'original_filename' => 'bass.pdf',
            'storage_reference' => 'charts/1/'.$songCode.'/bass.pdf',
            'checksum' => hash('sha256', 'bass'),
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);

        SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
            'chart_id' => $chart->id,
        ]);

        SongAsset::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'asset_type' => 'reference_track',
            'label' => 'Reference MP3',
            'storage_disk' => 'library',
            'storage_reference' => 'assets/1/'.$songCode.'/reference.mp3',
            'original_filename' => 'reference.mp3',
            'checksum' => hash('sha256', 'reference'),
            'mime_type' => 'audio/mpeg',
            'file_size' => 500,
            'sort_order' => 1,
        ]);

        return $song->fresh();
    }

    private function seedReferenceTables(): void
    {
        if (SongMood::query()->exists()) {
            return;
        }

        SongMood::query()->create([
            'name' => 'Happy',
            'slug' => 'happy',
            'colour_hex' => '#FFB23E',
            'accent_colour_hex' => '#FFD27A',
            'sort_order' => 20,
            'active' => true,
        ]);

        TimeSignature::query()->create(['label' => '4/4', 'sort_order' => 10, 'active' => true]);
        MusicalKey::query()->create(['label' => 'G major', 'tonic' => 'G', 'mode' => 'major', 'sort_order' => 10, 'active' => true]);
    }
}
