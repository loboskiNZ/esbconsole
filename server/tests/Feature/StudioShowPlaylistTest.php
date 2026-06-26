<?php

namespace Tests\Feature;

use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\MusicalKey;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\User;
use App\Services\StudioShowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    }

    public function test_playlist_displays_songs_with_metadata_and_instrument_parts(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Playlist Show']);
        $song = $this->seedSongWithParts('Shadows Intro', withChartFor: ['Lead Vocal', 'Trumpet'], withoutChartFor: ['Keyboard']);
        $this->seedPlaylistItem($show, $song, position: 1);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Shadows Intro', false)
            ->assertSee('120', false)
            ->assertSee('4/4', false)
            ->assertSee('G major', false)
            ->assertSee('Happy', false)
            ->assertSee('Lead Vocal', false)
            ->assertSee('Trumpet', false)
            ->assertSee('Keyboard', false)
            ->assertSee('✓ Chart', false)
            ->assertSee('Instrument parts', false);
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
            ->post(route('studio.shows.playlist.store', $show), [
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
        $firstItem = $this->seedPlaylistItem($show, $first, position: 1);
        $secondItem = $this->seedPlaylistItem($show, $second, position: 2);

        $this->actingAs($director)->get(route('studio.shows.show', $show))->assertOk();

        $this->actingAs($director)
            ->patch(route('studio.shows.playlist.move-down', [$show, $firstItem]), ['_token' => session()->token()])
            ->assertRedirect();

        $this->assertSame(2, ShowPlaylistItem::query()->find($firstItem->id)?->position);
        $this->assertSame(1, ShowPlaylistItem::query()->find($secondItem->id)?->position);
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
            ->assertDontSee('Move up', false);

        $this->actingAs($musician)
            ->post(route('studio.shows.playlist.store', $show), [
                '_token' => csrf_token(),
                'song_id' => $song->id,
            ])
            ->assertForbidden();

        $this->actingAs($musician)
            ->patch(route('studio.shows.playlist.archive', [$show, $item]), ['_token' => csrf_token()])
            ->assertForbidden();
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

    public function test_no_delete_route_exists_for_show_playlist_items(): void
    {
        $deleteRoutes = collect(app('router')->getRoutes())->filter(
            static fn ($route): bool => in_array('DELETE', $route->methods(), true)
                && str_contains($route->uri(), 'studio/shows')
                && str_contains($route->uri(), 'playlist')
        );

        $this->assertTrue($deleteRoutes->isEmpty());
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

    private function seedPlaylistItem(Show $show, Song $song, int $position = 1, ?string $notes = null): ShowPlaylistItem
    {
        return ShowPlaylistItem::query()->create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => $position,
            'notes' => $notes,
            'is_active' => true,
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
