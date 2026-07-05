<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
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
use App\Services\StudioShowPlaylistService;
use App\Services\StudioShowService;
use App\Support\InstrumentCatalog;
use App\Support\SongAssetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioShowPlaylistTest extends TestCase
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

        Storage::fake('media');
        Storage::fake('library');
        Storage::fake('local');
    }

    public function test_show_overview_uses_three_column_summary_layout(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Three Column Show']);
        $song = $this->seedSongWithParts('Column Song', withChartFor: ['Bass']);
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Show summary', false)
            ->assertSee('esb-studio__show-overview-grid--library', false)
            ->assertSee('esb-studio__instrument-parts-summary-card', false)
            ->assertSee('esb-studio__show-section--playlist', false)
            ->assertSee('Distinct parts required', false);
    }

    public function test_available_chart_pill_links_to_chart_file(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Chart Pill Show']);
        $song = $this->seedSongWithParts('Chart Pill Song', withChartFor: ['Bass']);
        $this->seedPlaylistItem($show, $song);
        $chart = Chart::query()->where('song_id', $song->id)->firstOrFail();

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee(route('studio.charts.file', $chart), false)
            ->assertSee('esb-studio__part-pill--available', false);
    }

    public function test_missing_chart_pill_links_to_scoped_upload_for_director(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Upload Pill Show']);
        $song = $this->seedSongWithParts('Upload Pill Song', withoutChartFor: ['Keyboard']);
        $this->seedPlaylistItem($show, $song);

        $songInstrumentPart = SongInstrumentPart::query()
            ->where('song_id', $song->id)
            ->whereHas('instrumentPart', fn ($query) => $query->where('name', 'Keyboard'))
            ->firstOrFail();

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee(route('studio.shows.playlist.chart.upload.create', [
                'show' => $show,
                'song' => $song,
                'songInstrumentPart' => $songInstrumentPart,
                'return_to' => '/studio/shows/'.$show->id.'#playlist',
            ]), false)
            ->assertSee('esb-studio__part-pill--missing', false);
    }

    public function test_musician_does_not_see_upload_link_for_missing_chart_pill(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $musician->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $show = $this->seedShow(['name' => 'Musician Upload Show']);
        $song = $this->seedSongWithParts('Musician Upload Song', withoutChartFor: ['Keyboard']);
        $this->seedPlaylistItem($show, $song);

        $songInstrumentPart = SongInstrumentPart::query()
            ->where('song_id', $song->id)
            ->whereHas('instrumentPart', fn ($query) => $query->where('name', 'Keyboard'))
            ->firstOrFail();

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Keyboard', false)
            ->assertDontSee(route('studio.shows.playlist.chart.upload.create', [
                'show' => $show,
                'song' => $song,
                'songInstrumentPart' => $songInstrumentPart,
            ]), false);
    }

    public function test_director_sees_edit_pill_notes_and_all_chart_pills(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Director Visibility Show']);
        $song = $this->seedSongWithParts('Director Song', withChartFor: ['Bass'], withoutChartFor: ['Keyboard']);
        $this->seedPlaylistItem($show, $song, notes: 'Director-only note.');

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee(route('songs.edit', ['song' => $song, 'return_to' => '/studio/shows/'.$show->id.'#playlist']), false)
            ->assertSee('Save notes', false)
            ->assertSee('Director-only note.', false)
            ->assertSee('Bass', false)
            ->assertSee('Keyboard', false)
            ->assertSee('esb-studio__setlist-order-badge--draggable', false);
    }

    public function test_musician_only_sees_chart_pills_for_assigned_instrument_parts(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $musician->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $show = $this->seedShow(['name' => 'Assigned Parts Show']);
        $song = $this->seedSongWithParts('Assigned Song', withChartFor: ['Keyboard', 'Bass']);
        $this->seedPlaylistItem($show, $song);

        $keyboardChart = Chart::query()
            ->where('song_id', $song->id)
            ->whereHas('songInstrumentParts.instrumentPart', fn ($query) => $query->where('name', 'Keyboard'))
            ->firstOrFail();
        $bassChart = Chart::query()
            ->where('song_id', $song->id)
            ->whereHas('songInstrumentParts.instrumentPart', fn ($query) => $query->where('name', 'Bass'))
            ->firstOrFail();

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Assigned Song', false)
            ->assertSee('Keyboard', false)
            ->assertSee(route('studio.charts.file', $keyboardChart), false)
            ->assertDontSee(route('studio.charts.file', $bassChart), false)
            ->assertDontSee(route('songs.edit', $song), false)
            ->assertDontSee('Save notes', false)
            ->assertDontSee('esb-studio__setlist-order-badge--draggable', false);
    }

    public function test_musician_with_no_assigned_parts_sees_no_chart_pills(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'No Assignment Show']);
        $song = $this->seedSongWithParts('Unassigned Song', withChartFor: ['Bass', 'Keyboard']);
        $this->seedPlaylistItem($show, $song);

        $bassChart = Chart::query()
            ->where('song_id', $song->id)
            ->whereHas('songInstrumentParts.instrumentPart', fn ($query) => $query->where('name', 'Bass'))
            ->firstOrFail();

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Unassigned Song', false)
            ->assertDontSee('Required parts', false)
            ->assertDontSee(route('studio.charts.file', $bassChart), false);
    }

    public function test_musician_cannot_access_unassigned_chart_file(): void
    {
        Storage::fake('library');
        config(['portal.library_chart_disk' => 'library']);

        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $musician->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithParts('Chart Access Song', withChartFor: ['Keyboard', 'Bass']);
        $bassChart = Chart::query()
            ->where('song_id', $song->id)
            ->whereHas('songInstrumentParts.instrumentPart', fn ($query) => $query->where('name', 'Bass'))
            ->firstOrFail();
        Storage::disk('library')->put($bassChart->storage_reference, '%PDF-1.4 bass chart');

        $this->actingAs($musician)->get(route('studio.charts.file', $bassChart))
            ->assertForbidden();
    }

    public function test_musician_can_access_assigned_chart_file(): void
    {
        Storage::fake('library');
        config(['portal.library_chart_disk' => 'library']);

        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $musician->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithParts('Assigned Chart Song', withChartFor: ['Keyboard']);
        $keyboardChart = Chart::query()->where('song_id', $song->id)->firstOrFail();
        Storage::disk('library')->put($keyboardChart->storage_reference, '%PDF-1.4 keyboard chart');

        $response = $this->actingAs($musician)->get(route('studio.charts.file', $keyboardChart));

        $response->assertOk();
        $this->assertStringContainsString('%PDF-1.4 keyboard chart', $response->streamedContent());
    }

    public function test_director_can_upload_chart_for_missing_song_instrument_part(): void
    {
        Storage::fake('media');
        Storage::fake('library');
        config(['portal.library_chart_disk' => 'library']);

        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Upload Flow Show']);
        $song = $this->seedSongWithParts('Upload Flow Song', withoutChartFor: ['Keyboard']);
        $this->seedPlaylistItem($show, $song);
        $playlistNotes = 'Keep playlist note';

        $songInstrumentPart = SongInstrumentPart::query()
            ->where('song_id', $song->id)
            ->whereHas('instrumentPart', fn ($query) => $query->where('name', 'Keyboard'))
            ->firstOrFail();

        $item = ShowPlaylistItem::query()->where('show_id', $show->id)->firstOrFail();
        $item->update(['notes' => $playlistNotes]);

        $returnTo = '/studio/shows/'.$show->id.'#playlist';
        $chartCountBefore = Chart::query()->count();

        $this->actingAs($director)->get(route('studio.shows.playlist.chart.upload.create', [
            'show' => $show,
            'song' => $song,
            'songInstrumentPart' => $songInstrumentPart,
            'return_to' => $returnTo,
        ]))->assertOk()
            ->assertSee($returnTo, false)
            ->assertSee('Upload chart', false)
            ->assertSee($song->name, false)
            ->assertSee('Keyboard', false);

        $this->actingAs($director)->post(route('studio.shows.playlist.chart.upload.store', [
            'show' => $show,
            'song' => $song,
            'songInstrumentPart' => $songInstrumentPart,
        ]), [
            '_token' => session()->token(),
            'chart' => UploadedFile::fake()->create('keyboard.pdf', 120, 'application/pdf'),
            'return_to' => $returnTo,
        ])->assertRedirect($returnTo);

        $songInstrumentPart->refresh();
        $this->assertNotNull($songInstrumentPart->chart_id);
        $this->assertSame($chartCountBefore + 1, Chart::query()->count());
        $this->assertSame($playlistNotes, $item->fresh()->notes);
        $this->assertSame(
            SongInstrumentPart::query()->count(),
            SongInstrumentPart::query()->where('song_id', $song->id)->count(),
        );
    }

    public function test_playlist_displays_songs_with_metadata_and_instrument_parts(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Playlist Show']);
        $song = $this->seedSongWithParts('Shadows Intro', withChartFor: ['Lead Vocal', 'Trumpet'], withoutChartFor: ['Keyboard']);
        $this->seedPlaylistItem($show, $song, position: 1);

        $response = $this->actingAs($director)->get(route('studio.shows.show', $show));

        $response->assertOk()
            ->assertSee('esb-studio__setlist-ribbon', false)
            ->assertSee('esb-studio__setlist-ribbon-header', false)
            ->assertSee('esb-studio__setlist-ribbon-details', false)
            ->assertSee('Shadows Intro', false)
            ->assertDontSee('esb-studio__setlist-order-label', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Expand song details', false)
            ->assertSee('120', false)
            ->assertSee('4/4', false)
            ->assertSee('G major', false)
            ->assertSee('Happy', false)
            ->assertSee('Lead Vocal', false)
            ->assertSee('Trumpet', false)
            ->assertSee('Keyboard', false)
            ->assertSee('📄✓', false)
            ->assertSee('📄✕', false)
            ->assertSee('Required parts', false)
            ->assertSee('Save notes', false)
            ->assertSee('esb-studio__setlist-order-badge--draggable', false)
            ->assertSee('Playlist summary', false);

        $this->assertMatchesRegularExpression(
            '/data-playlist-order-badge[^>]*>\s*01\s*</',
            (string) $response->getContent(),
        );

        $this->assertMatchesRegularExpression(
            '/id="playlist-details-\d+"[^>]*hidden/s',
            (string) $response->getContent(),
        );
    }

    public function test_playlist_order_number_appears_once_in_ribbon_header(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Order Label Show']);
        $song = $this->seedSongWithParts('Electronic Love');
        $this->seedPlaylistItem($show, $song, position: 3);

        $response = $this->actingAs($director)->get(route('studio.shows.show', $show));

        $html = (string) $response->getContent();

        $response->assertOk()
            ->assertSee('>Electronic Love<', false)
            ->assertDontSee('esb-studio__setlist-order-label', false)
            ->assertDontSee('03 · Electronic Love', false);

        $this->assertMatchesRegularExpression('/data-playlist-order-badge[^>]*>\s*03\s*</', $html);
        $this->assertSame(
            1,
            preg_match_all('/data-playlist-order-badge[^>]*>\s*03\s*</', $html),
        );
    }

    public function test_playlist_ribbon_exposes_expand_controls_for_accessibility(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Expand Control Show']);
        $song = $this->seedSongWithParts('Expandable Song');
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('esb-studio__setlist-toggle', false)
            ->assertSee('aria-controls="playlist-details-', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Expand song details', false)
            ->assertSee('Collapse song details', false);
    }

    public function test_musician_does_not_see_playlist_notes_editor(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'Musician Notes Show']);
        $song = $this->seedSongWithParts('Noted Song');
        $this->seedPlaylistItem($show, $song, notes: 'Watch the intro vamp.');

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Noted Song', false)
            ->assertSee('Watch the intro vamp.', false)
            ->assertSee('esb-studio__playlist-song-notes', false)
            ->assertDontSee('esb-studio__playlist-item-notes', false)
            ->assertDontSee('Save notes', false);
    }

    public function test_playlist_displays_song_and_playlist_notes_for_all_users(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $show = $this->seedShow(['name' => 'Song Notes Show']);
        $withNotes = $this->seedSongWithParts('Corazón');
        $withNotes->update([
            'notes' => "Intro starts with percussion only.\nLeave space before verse.",
            'director_notes' => 'Director-only production guidance.',
        ]);
        $plain = $this->seedSongWithParts('Plain Song');
        $this->seedPlaylistItem($show, $withNotes, position: 1, notes: 'Gat chords: Intro A');
        $this->seedPlaylistItem($show, $plain, position: 2);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Song notes', false)
            ->assertSee('Intro starts with percussion only.', false)
            ->assertSee('Gat chords: Intro A', false)
            ->assertSee('esb-studio__playlist-item-notes', false)
            ->assertDontSee('Director-only production guidance', false);

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Song notes', false)
            ->assertSee('Gat chords: Intro A', false)
            ->assertSee('Intro starts with percussion only.', false)
            ->assertDontSee('Director-only production guidance', false)
            ->assertDontSee('esb-studio__playlist-item-notes', false)
            ->assertDontSee('Save notes', false);
    }

    public function test_playlist_hides_song_notes_when_empty(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Empty Song Notes Show']);
        $song = $this->seedSongWithParts('No Notes Song');
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertDontSee('esb-studio__playlist-song-notes', false);
    }

    public function test_playlist_summary_counts_are_correct(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Summary Show']);

        $songA = $this->seedSongWithParts('Song A', withChartFor: ['Bass', 'Trumpet'], withoutChartFor: ['Drums']);
        $songB = $this->seedSongWithParts('Song B', withChartFor: ['Bass'], withoutChartFor: []);
        $this->seedPlaylistItem($show, $songA, position: 1);
        $this->seedPlaylistItem($show, $songB, position: 2);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Playlist summary', false)
            ->assertSee('Songs', false)
            ->assertSee('Charts missing', false);

        $summary = app(StudioShowPlaylistService::class)->playlistViewForShow($show->id)['summary'];

        $this->assertSame(2, $summary['song_count']);
        $this->assertSame(3, $summary['instrument_part_count']);
        $this->assertSame(3, $summary['charts_available']);
        $this->assertSame(1, $summary['charts_missing']);
        $this->assertSame(3, $summary['charts_count']);
        $this->assertSame('Unknown', $summary['estimated_duration_label']);
        $this->assertNull($summary['estimated_duration_seconds']);
    }

    public function test_distinct_instrument_parts_appear_once_in_overview(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Distinct Parts Show']);

        $songA = $this->seedSongWithParts('Song A', withChartFor: ['Bass']);
        $songB = $this->seedSongWithParts('Song B', withChartFor: ['Bass', 'Trumpet']);
        $this->seedPlaylistItem($show, $songA);
        $this->seedPlaylistItem($show, $songB, position: 2);

        $view = app(StudioShowPlaylistService::class)->playlistViewForShow($show->id);

        $this->assertSame(['Bass', 'Trumpet'], collect($view['show_instrument_parts'])->pluck('name')->all());
        $this->assertCount(2, $view['entries']);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Distinct parts required', false)
            ->assertSee('Trumpet', false);
    }

    public function test_archived_playlist_items_do_not_contribute_to_summary(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Archived Summary Show']);

        $active = $this->seedSongWithParts('Active Song', withChartFor: ['Bass']);
        $archived = $this->seedSongWithParts('Archived Song', withChartFor: ['Trumpet', 'Drums']);
        $this->seedPlaylistItem($show, $active);
        $this->seedPlaylistItem($show, $archived, position: 2, isActive: false);

        $summary = app(StudioShowPlaylistService::class)->playlistViewForShow($show->id)['summary'];

        $this->assertSame(1, $summary['song_count']);
        $this->assertSame(1, $summary['instrument_part_count']);
        $this->assertSame(1, $summary['charts_available']);
        $this->assertSame(0, $summary['charts_missing']);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Active Song', false)
            ->assertSee('esb-studio__playlist-song-title', false)
            ->assertDontSee('esb-studio__playlist-song-title">Archived Song', false);
    }

    public function test_musicians_see_only_assigned_playlist_chart_pills(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $bassRef = InstrumentReference::query()->where('slug', 'scaffold-bass-guitar')->firstOrFail();
        $musician->person->instruments()->attach($bassRef->id, ['is_primary' => true]);

        $show = $this->seedShow(['name' => 'Musician Read Show']);
        $song = $this->seedSongWithParts('Readable Song', withChartFor: ['Bass'], withoutChartFor: ['Drums']);
        $this->seedPlaylistItem($show, $song);

        $bassChart = Chart::query()
            ->where('song_id', $song->id)
            ->whereHas('songInstrumentParts.instrumentPart', fn ($query) => $query->where('name', 'Bass'))
            ->firstOrFail();

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Playlist summary', false)
            ->assertSee('Required parts', false)
            ->assertSee('📄✓', false)
            ->assertSee('Bass chart available', false)
            ->assertSee(route('studio.charts.file', $bassChart), false)
            ->assertDontSee('Drums', false)
            ->assertDontSee('📄✕', false)
            ->assertDontSee('Add song', false)
            ->assertDontSee(route('songs.edit', $song), false);
    }

    public function test_playlist_song_ribbon_shows_external_link_icons_when_urls_exist(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $show = $this->seedShow(['name' => 'External Links Show']);
        $linked = $this->seedSongWithParts('Linked Song');
        $linked->update([
            'spotify_url' => 'https://open.spotify.com/track/playlist-link-test',
            'youtube_url' => 'https://www.youtube.com/watch?v=playlist-link-test',
        ]);
        $plain = $this->seedSongWithParts('Plain Song');
        $this->seedPlaylistItem($show, $linked, position: 1);
        $this->seedPlaylistItem($show, $plain, position: 2);

        $directorResponse = $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $directorResponse
            ->assertSee('aria-label="Open Spotify for Linked Song"', false)
            ->assertSee('aria-label="Open YouTube for Linked Song"', false)
            ->assertSee('href="https://open.spotify.com/track/playlist-link-test"', false)
            ->assertSee('href="https://www.youtube.com/watch?v=playlist-link-test"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('esb-studio__playlist-song-link--spotify', false)
            ->assertSee('esb-studio__playlist-song-link--youtube', false)
            ->assertDontSee('aria-label="Open Spotify for Plain Song"', false)
            ->assertDontSee('aria-label="Open YouTube for Plain Song"', false);

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('aria-label="Open Spotify for Linked Song"', false)
            ->assertSee('aria-label="Open YouTube for Linked Song"', false);
    }

    public function test_show_overview_does_not_modify_library_or_playlist_rows(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Read Only Show']);
        $song = $this->seedSongWithParts('Read Only Song', withChartFor: ['Bass']);
        $item = $this->seedPlaylistItem($show, $song);

        $songCount = Song::query()->count();
        $chartCount = Chart::query()->count();
        $partCount = SongInstrumentPart::query()->count();
        $playlistCount = DB::table('show_playlist_items')->count();

        $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $this->assertSame($songCount, Song::query()->count());
        $this->assertSame($chartCount, Chart::query()->count());
        $this->assertSame($partCount, SongInstrumentPart::query()->count());
        $this->assertSame($playlistCount, DB::table('show_playlist_items')->count());
        $this->assertDatabaseHas('show_playlist_items', [
            'id' => $item->id,
            'is_active' => true,
            'position' => 1,
        ]);
    }

    public function test_playlist_shows_no_instrument_parts_message_when_none_exist(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Empty Parts Show']);
        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '099',
            'name' => 'No Parts Song',
            'bpm' => 100,
            'status' => 'ready',
        ]);
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('No instrument parts defined.', false);
    }

    public function test_director_can_add_song_to_playlist(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Add Song Show']);
        $song = $this->seedSongWithParts('Added Song');

        $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $this->actingAs($director)
            ->post(route('studio.shows.playlist.items.store', $show), [
                '_token' => session()->token(),
                'song_id' => $song->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('playlist_updated');

        $this->assertDatabaseHas('show_playlist_items', [
            'show_id' => $show->id,
            'song_id' => $song->id,
            'is_active' => true,
        ]);
    }

    public function test_director_can_reorder_playlist_items(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Reorder Show']);
        $first = $this->seedSongWithParts('First Song');
        $second = $this->seedSongWithParts('Second Song');
        $third = $this->seedSongWithParts('Third Song');
        $firstItem = $this->seedPlaylistItem($show, $first, position: 1);
        $secondItem = $this->seedPlaylistItem($show, $second, position: 2);
        $thirdItem = $this->seedPlaylistItem($show, $third, position: 3);

        $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $this->actingAs($director)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->patchJson(route('studio.shows.playlist.reorder', $show), [
                'order' => [$thirdItem->id, $firstItem->id, $secondItem->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('positions.'.$thirdItem->id, 1)
            ->assertJsonPath('positions.'.$firstItem->id, 2)
            ->assertJsonPath('positions.'.$secondItem->id, 3);

        $this->assertSame(1, ShowPlaylistItem::query()->find($thirdItem->id)?->position);
        $this->assertSame(2, ShowPlaylistItem::query()->find($firstItem->id)?->position);
        $this->assertSame(3, ShowPlaylistItem::query()->find($secondItem->id)?->position);

        $refresh = $this->actingAs($director)->get(route('studio.shows.show', $show));

        $refresh->assertOk()
            ->assertSee('Third Song', false);

        $this->assertMatchesRegularExpression(
            '/data-playlist-order-badge[^>]*>\s*01\s*</',
            (string) $refresh->getContent(),
        );
    }

    public function test_musician_cannot_reorder_playlist_items(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'Locked Reorder Show']);
        $first = $this->seedSongWithParts('First Song');
        $second = $this->seedSongWithParts('Second Song');
        $firstItem = $this->seedPlaylistItem($show, $first, position: 1);
        $secondItem = $this->seedPlaylistItem($show, $second, position: 2);

        $this->actingAs($musician)->get(route('studio.shows.show', $show));

        $this->actingAs($musician)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->patchJson(route('studio.shows.playlist.reorder', $show), [
                'order' => [$secondItem->id, $firstItem->id],
            ])
            ->assertForbidden();

        $this->assertSame(1, ShowPlaylistItem::query()->find($firstItem->id)?->position);
        $this->assertSame(2, ShowPlaylistItem::query()->find($secondItem->id)?->position);
    }

    public function test_director_can_remove_playlist_item_without_deleting_song_or_library_data(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Remove Show']);
        $song = $this->seedSongWithParts('Removable Song', withChartFor: ['Bass']);
        $item = $this->seedPlaylistItem($show, $song);
        $songCount = Song::query()->count();
        $chartCount = Chart::query()->count();
        $partCount = SongInstrumentPart::query()->count();
        $rowCount = DB::table('show_playlist_items')->count();

        $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $this->actingAs($director)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->deleteJson(route('studio.shows.playlist.items.destroy', [$show, $item]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.song_count', 0);

        $this->assertSame($rowCount, DB::table('show_playlist_items')->count());
        $this->assertDatabaseHas('show_playlist_items', [
            'id' => $item->id,
            'is_active' => false,
        ]);
        $this->assertSame($songCount, Song::query()->count());
        $this->assertSame($chartCount, Chart::query()->count());
        $this->assertSame($partCount, SongInstrumentPart::query()->count());
        $this->assertTrue(Song::query()->whereKey($song->id)->exists());

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertDontSee('esb-studio__playlist-song-title">Removable Song', false);
    }

    public function test_director_can_archive_playlist_item_without_deleting_row(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Archive Show']);
        $song = $this->seedSongWithParts('Archived Song');
        $item = $this->seedPlaylistItem($show, $song);
        $countBefore = DB::table('show_playlist_items')->count();

        $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $this->actingAs($director)
            ->patch(route('studio.shows.playlist.archive', [$show, $item]), ['_token' => session()->token()])
            ->assertRedirect();

        $this->assertSame($countBefore, DB::table('show_playlist_items')->count());
        $this->assertDatabaseHas('show_playlist_items', [
            'id' => $item->id,
            'is_active' => false,
        ]);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('No songs on this playlist yet.', false);
    }

    public function test_musician_can_view_playlist_but_cannot_edit_it(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'Musician Playlist Show']);
        $song = $this->seedSongWithParts('Readable Song');
        $item = $this->seedPlaylistItem($show, $song);

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Readable Song', false)
            ->assertSee('Instrument parts', false)
            ->assertDontSee('Add song', false)
            ->assertDontSee('Remove', false)
            ->assertDontSee('esb-studio__setlist-order-badge--draggable', false)
            ->assertDontSee(route('songs.edit', $song), false);

        $this->actingAs($musician)->get(route('studio.shows.index'));

        $this->actingAs($musician)
            ->post(route('studio.shows.playlist.items.store', $show), [
                '_token' => session()->token(),
                'song_id' => $song->id,
            ])
            ->assertForbidden();

        $this->actingAs($musician)
            ->delete(route('studio.shows.playlist.items.destroy', [$show, $item]), [
                '_token' => session()->token(),
            ])
            ->assertForbidden();

        $this->actingAs($musician)->get(route('studio.shows.show', $show));

        $this->actingAs($musician)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->patchJson(route('studio.shows.playlist.reorder', $show), [
                'order' => [$item->id],
            ])
            ->assertForbidden();

        $this->actingAs($musician)->get(route('songs.edit', $song))->assertForbidden();
    }

    public function test_director_sees_edit_pill_beside_playlist_song_title(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Edit Pill Show']);
        $song = $this->seedSongWithParts('Editable Song');
        $this->seedPlaylistItem($show, $song);

        $returnTo = '/studio/shows/'.$show->id.'#playlist';

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Editable Song', false)
            ->assertSee(route('songs.edit', ['song' => $song, 'return_to' => $returnTo]), false);
    }

    public function test_song_edit_page_preserves_return_to_from_playlist(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Return To Show']);
        $song = $this->seedSongWithParts('Return Song');
        $returnTo = '/studio/shows/'.$show->id.'#playlist';

        $this->actingAs($director)->get(route('songs.edit', ['song' => $song, 'return_to' => $returnTo]))
            ->assertOk()
            ->assertSee('name="return_to"', false)
            ->assertSee($returnTo, false);
    }

    public function test_saving_song_from_playlist_redirects_back_to_show_playlist(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Save Return Show']);
        $song = $this->seedSongWithParts('Before Save');
        $item = $this->seedPlaylistItem($show, $song, notes: 'Keep this note.');
        $returnTo = '/studio/shows/'.$show->id.'#playlist';
        $playlistNotesBefore = ShowPlaylistItem::query()->find($item->id)?->notes;
        $chartCount = Chart::query()->count();
        $partCount = SongInstrumentPart::query()->count();

        $this->actingAs($director)->get(route('songs.edit', ['song' => $song, 'return_to' => $returnTo]));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => 'After Save',
            'bpm' => 128,
            'return_to' => $returnTo,
        ])->assertRedirect($returnTo);

        $this->assertSame('After Save', Song::query()->find($song->id)?->name);
        $this->assertSame($playlistNotesBefore, ShowPlaylistItem::query()->find($item->id)?->notes);
        $this->assertSame($chartCount, Chart::query()->count());
        $this->assertSame($partCount, SongInstrumentPart::query()->count());

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('After Save', false)
            ->assertSee('128', false);
    }

    public function test_unsafe_external_return_to_is_ignored_on_song_update(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSongWithParts('Safe Redirect Song');
        $fallback = route('studio.charts.show', $song, absolute: false);

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => 'Safe Redirect Song',
            'return_to' => 'https://evil.example/phish',
        ])->assertRedirect($fallback);
    }

    public function test_playlist_management_does_not_modify_song_or_chart_data(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Immutable Library Show']);
        $song = $this->seedSongWithParts('Immutable Song');
        $item = $this->seedPlaylistItem($show, $song, notes: 'Original playlist note.');
        $songCount = Song::query()->count();
        $chartCount = Chart::query()->count();
        $partCount = SongInstrumentPart::query()->count();

        $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $this->actingAs($director)
            ->patch(route('studio.shows.playlist.notes', [$show, $item]), [
                '_token' => session()->token(),
                'notes' => 'Updated playlist note.',
            ])
            ->assertRedirect();

        $this->assertSame($songCount, Song::query()->count());
        $this->assertSame($chartCount, Chart::query()->count());
        $this->assertSame($partCount, SongInstrumentPart::query()->count());
        $this->assertSame('Immutable Song', Song::query()->find($song->id)?->name);
    }

    public function test_playlist_remove_route_archives_item(): void
    {
        $deleteRoutes = collect(app('router')->getRoutes())->filter(
            static fn ($route): bool => in_array('DELETE', $route->methods(), true)
                && $route->getName() === 'studio.shows.playlist.items.destroy'
        );

        $this->assertCount(1, $deleteRoutes);
    }

    public function test_searchable_song_picker_renders_for_director(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Picker Show']);
        $this->seedSongWithParts('Picker Song');

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('esb-studio__playlist-picker', false)
            ->assertSee('id="playlist-song-picker-search"', false)
            ->assertSee('Add song to playlist', false)
            ->assertSee('data-playlist-summary-songs', false)
            ->assertSee('Estimated duration', false);
    }

    public function test_playlist_song_search_filters_by_name_and_code(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Search Show']);

        Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '042',
            'name' => 'Electronic Love',
            'status' => 'ready',
        ]);

        Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '099',
            'name' => 'Acoustic Morning',
            'reference_title' => 'Original Artist',
            'status' => 'ready',
        ]);

        $this->actingAs($director)->getJson(route('studio.shows.playlist.songs.search', [
            'show' => $show,
            'q' => '042',
        ]))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Electronic Love');

        $this->actingAs($director)->getJson(route('studio.shows.playlist.songs.search', [
            'show' => $show,
            'q' => 'electronic',
        ]))
            ->assertOk()
            ->assertJsonPath('results.0.song_code', '042');

        $this->actingAs($director)->getJson(route('studio.shows.playlist.songs.search', [
            'show' => $show,
            'q' => 'artist',
        ]))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Acoustic Morning');
    }

    public function test_duplicate_song_add_is_prevented_via_json(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Duplicate Show']);
        $song = $this->seedSongWithParts('Duplicate Song');
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show));

        $this->actingAs($director)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->postJson(route('studio.shows.playlist.items.store', $show), [
                'song_id' => $song->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Song is already on this playlist.');

        $this->assertSame(1, ShowPlaylistItem::query()->where('show_id', $show->id)->active()->count());
    }

    public function test_director_can_add_song_via_json_picker(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Json Add Show']);
        $song = $this->seedSongWithParts('Json Added Song', withChartFor: ['Bass']);

        $this->actingAs($director)->get(route('studio.shows.show', $show));

        $response = $this->actingAs($director)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->postJson(route('studio.shows.playlist.items.store', $show), [
                'song_id' => $song->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.song_count', 1)
            ->assertJsonPath('summary.charts_count', 1);

        $this->assertStringContainsString('Json Added Song', (string) $response->json('html'));
        $this->assertStringContainsString('esb-studio__setlist-order-badge--draggable', (string) $response->json('html'));
        $this->assertDatabaseHas('show_playlist_items', [
            'show_id' => $show->id,
            'song_id' => $song->id,
            'is_active' => true,
        ]);
    }

    public function test_musician_cannot_access_playlist_song_search_or_json_add(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'Musician Picker Show']);
        $song = $this->seedSongWithParts('Hidden Song');
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertDontSee('esb-studio__playlist-picker', false)
            ->assertDontSee('playlist-song-picker-search', false);

        $this->actingAs($musician)->getJson(route('studio.shows.playlist.songs.search', [
            'show' => $show,
            'q' => 'hidden',
        ]))->assertForbidden();

        $this->actingAs($musician)->get(route('studio.shows.show', $show));

        $this->actingAs($musician)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->postJson(route('studio.shows.playlist.items.store', $show), [
                'song_id' => $song->id,
            ])
            ->assertForbidden();
    }

    public function test_playlist_inline_summary_renders_unknown_duration(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Duration Show']);
        $song = $this->seedSongWithParts('Duration Song', withChartFor: ['Bass']);
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('data-playlist-summary-duration', false)
            ->assertSee('Unknown', false);

        $service = app(StudioShowPlaylistService::class);
        $this->assertSame('Unknown', $service->formatEstimatedDurationLabel(null));
        $this->assertSame('02:08:35', $service->formatEstimatedDurationLabel(7715));
    }

    public function test_reorder_endpoint_returns_positions_for_client_feedback(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Feedback Show']);
        $first = $this->seedSongWithParts('First Song');
        $second = $this->seedSongWithParts('Second Song');
        $firstItem = $this->seedPlaylistItem($show, $first, position: 1);
        $secondItem = $this->seedPlaylistItem($show, $second, position: 2);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('id="playlist-order-feedback"', false);

        $this->actingAs($director)
            ->withHeader('X-CSRF-TOKEN', session()->token())
            ->patchJson(route('studio.shows.playlist.reorder', $show), [
                'order' => [$secondItem->id, $firstItem->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('positions.'.$secondItem->id, 1);
    }

    public function test_playlist_without_assets_unchanged(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'No Assets Show']);
        $song = $this->seedSongWithParts('Plain Song');
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertDontSee('esb-studio__playlist-song-assets', false);
    }

    public function test_playlist_displays_song_assets_with_download_links(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Assets Show']);
        $song = $this->seedSongWithParts('Asset Song');
        $mp3 = $this->seedSongAsset($song, 'Studio Mix', 'studio-mix.mp3', 'audio/mpeg', SongAssetType::AUDIO);
        $midi = $this->seedSongAsset($song, 'Horns MIDI', 'horns.mid', 'audio/midi', SongAssetType::MIDI);
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('esb-studio__playlist-song-assets', false)
            ->assertSee('Studio Mix', false)
            ->assertSee('Horns MIDI', false)
            ->assertSee(route('songs.assets.file', [$song, $mp3]), false)
            ->assertSee(route('songs.assets.file', [$song, $midi]), false)
            ->assertSee('esb-studio__playlist-asset-audio', false)
            ->assertDontSee('esb-studio__song-asset-upload-form', false);
    }

    public function test_playlist_shows_inline_audio_for_mp3_and_wav(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Audio Preview Show']);
        $song = $this->seedSongWithParts('Audio Song');
        $mp3 = $this->seedSongAsset($song, 'Full mix', 'full-mix.mp3', 'audio/mpeg', SongAssetType::AUDIO);
        $wav = $this->seedSongAsset($song, '', 'drums.wav', 'audio/wav', SongAssetType::STEM);
        $this->seedPlaylistItem($show, $song);

        $response = $this->actingAs($director)->get(route('studio.shows.show', $show));

        $response->assertOk()
            ->assertSee('type="audio/mpeg"', false)
            ->assertSee('type="audio/wav"', false)
            ->assertSee('aria-label="Preview Full mix"', false)
            ->assertSee('aria-label="Preview drums.wav"', false);

        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'esb-studio__playlist-asset-audio'));
        $this->assertStringContainsString(route('songs.assets.file', [$song, $mp3]), $html);
        $this->assertStringContainsString(route('songs.assets.file', [$song, $wav]), $html);
    }

    public function test_musician_can_view_and_download_playlist_song_assets(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $show = $this->seedShow(['name' => 'Musician Assets Show']);
        $song = $this->seedSongWithParts('Musician Asset Song');
        $asset = $this->seedSongAsset($song, 'Reference track', 'reference.mp3', 'audio/mpeg', SongAssetType::REFERENCE);
        $this->seedPlaylistItem($show, $song);

        Storage::disk('media')->put($asset->storage_reference, 'fake-audio-bytes');

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Reference track', false)
            ->assertSee('esb-studio__playlist-asset-audio', false)
            ->assertDontSee('esb-studio__song-asset-upload-form', false);

        $this->actingAs($musician)->get(route('songs.assets.file', [$song, $asset]))
            ->assertOk();

        $this->actingAs($musician)->post(route('songs.assets.store', $song), [
            '_token' => session()->token(),
            'file' => UploadedFile::fake()->create('blocked.mp3', 80, 'audio/mpeg'),
            'asset_type' => SongAssetType::AUDIO,
            'label' => 'Blocked upload',
        ])->assertForbidden();
    }

    /**
     * @param  list<string>  $withChartFor
     * @param  list<string>  $withoutChartFor
     */
    private function seedSongWithParts(
        string $name,
        array $withChartFor = ['Lead Vocal'],
        array $withoutChartFor = [],
    ): Song {
        $timeSignatureId = TimeSignature::query()->where('label', '4/4')->value('id');
        $musicalKeyId = MusicalKey::query()->where('label', 'G major')->value('id');
        $moodId = SongMood::query()->where('slug', 'happy')->value('id');

        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => (string) Str::padLeft((string) random_int(1, 999), 3, '0'),
            'name' => $name,
            'bpm' => 120,
            'time_signature_id' => $timeSignatureId,
            'musical_key_id' => $musicalKeyId,
            'mood_id' => $moodId,
            'status' => 'ready',
        ]);

        foreach ($withChartFor as $partName) {
            $this->attachSongPart($song, $partName, withChart: true);
        }

        foreach ($withoutChartFor as $partName) {
            $this->attachSongPart($song, $partName, withChart: false);
        }

        return $song->fresh(['timeSignature', 'musicalKey', 'mood', 'songInstrumentParts.instrumentPart']);
    }

    private function attachSongPart(Song $song, string $partName, bool $withChart): void
    {
        $part = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => $partName,
            'active' => true,
        ]);

        $chartId = null;
        if ($withChart) {
            $chart = Chart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'title' => $partName.' Chart',
                'original_filename' => Str::slug($partName).'.pdf',
                'storage_reference' => 'charts/1/'.$song->song_code.'/'.Str::slug($partName).'.pdf',
                'checksum' => hash('sha256', $partName),
                'mime_type' => 'application/pdf',
                'file_size' => 100,
            ]);
            $chartId = $chart->id;
        }

        SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
            'chart_id' => $chartId,
        ]);
    }

    private function seedPlaylistItem(
        Show $show,
        Song $song,
        int $position = 1,
        ?string $notes = null,
        bool $isActive = true,
    ): ShowPlaylistItem {
        return ShowPlaylistItem::query()->create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => $position,
            'notes' => $notes,
            'is_active' => $isActive,
        ]);
    }

    private function seedSongAsset(
        Song $song,
        string $label,
        string $originalFilename,
        string $mimeType,
        string $assetType,
    ): SongAsset {
        $storageReference = 'library/songs/'.$song->id.'/assets/'.$assetType.'/'.$originalFilename;

        Storage::disk('media')->put($storageReference, 'seeded-bytes');

        return SongAsset::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'asset_type' => $assetType,
            'label' => $label,
            'storage_disk' => 'media',
            'storage_reference' => $storageReference,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size' => 1024,
            'checksum' => hash('sha256', $originalFilename),
            'sort_order' => (int) SongAsset::query()->where('song_id', $song->id)->max('sort_order') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedShow(array $attributes = []): Show
    {
        return app(StudioShowService::class)->createShow(array_merge([
            'name' => 'Seed Show',
            'lifecycle_state' => Show::STATE_DRAFT,
        ], $attributes));
    }

    private function seedReferenceTables(): void
    {
        if (! SongMood::query()->exists()) {
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

        if (! InstrumentReference::query()->exists()) {
            foreach (InstrumentCatalog::definitions() as $instrument) {
                InstrumentReference::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'slug' => $instrument['slug'],
                    'name' => $instrument['name'],
                    'family' => $instrument['family'] ?? null,
                    'is_active' => true,
                ]);
            }
        }
    }
}
