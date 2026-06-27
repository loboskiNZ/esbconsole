<?php

namespace Tests\Feature;

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
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioSongInstrumentPartTest extends TestCase
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

    public function test_song_edit_page_displays_existing_instrument_parts(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Parts Song');
        $this->attachPart($song, 'Bass');
        $this->attachPart($song, 'Trumpet');

        $this->actingAs($director)->get(route('songs.edit', $song))
            ->assertOk()
            ->assertSee('Instrument parts', false)
            ->assertSee('Bass', false)
            ->assertSee('Trumpet', false)
            ->assertSee('📄✕', false);
    }

    public function test_director_can_attach_existing_instrument_part_from_song_edit(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Attach Song');
        $existingPart = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Keyboard',
            'active' => true,
        ]);

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->post(route('songs.instrument-parts.store', $song), [
            '_token' => session()->token(),
            'instrument_part_id' => $existingPart->id,
        ])->assertRedirect(route('songs.edit', $song, absolute: false))
            ->assertSessionHas('song_part_added');

        $this->assertTrue(
            SongInstrumentPart::query()
                ->where('song_id', $song->id)
                ->where('instrument_part_id', $existingPart->id)
                ->exists(),
        );
    }

    public function test_director_can_create_and_attach_new_instrument_part(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Create Part Song');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->post(route('songs.instrument-parts.store', $song), [
            '_token' => session()->token(),
            'new_part_name' => 'Alto Sax',
        ])->assertRedirect(route('songs.edit', $song, absolute: false))
            ->assertSessionHas('song_part_added');

        $part = InstrumentPart::query()->where('name', 'Alto Sax')->first();
        $this->assertNotNull($part);

        $this->assertTrue(
            SongInstrumentPart::query()
                ->where('song_id', $song->id)
                ->where('instrument_part_id', $part->id)
                ->exists(),
        );
    }

    public function test_adding_part_preserves_return_to_on_song_edit_redirect(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Return Show']);
        $song = $this->seedSong('Return Song');
        $this->seedPlaylistItem($show, $song);
        $returnTo = '/studio/shows/'.$show->id.'#playlist';

        $this->actingAs($director)->get(route('songs.edit', ['song' => $song, 'return_to' => $returnTo]));

        $this->actingAs($director)->post(route('songs.instrument-parts.store', $song), [
            '_token' => session()->token(),
            'new_part_name' => 'Percussion',
            'return_to' => $returnTo,
        ])->assertRedirect(route('songs.edit', ['song' => $song, 'return_to' => $returnTo], absolute: false));
    }

    public function test_song_metadata_update_still_redirects_to_show_playlist_return_to(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Metadata Return Show']);
        $song = $this->seedSong('Metadata Return Song');
        $this->seedPlaylistItem($show, $song);
        $returnTo = '/studio/shows/'.$show->id.'#playlist';

        $this->actingAs($director)->get(route('songs.edit', ['song' => $song, 'return_to' => $returnTo]));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => 'Updated Metadata Song',
            'return_to' => $returnTo,
        ])->assertRedirect($returnTo);

        $this->assertSame('Updated Metadata Song', Song::query()->find($song->id)?->name);
    }

    public function test_musician_cannot_add_instrument_part(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $song = $this->seedSong('Locked Song');

        $this->actingAs($musician)->get(route('songs.edit', $song))->assertForbidden();

        $this->actingAs($musician)->post(route('songs.instrument-parts.store', $song), [
            '_token' => csrf_token(),
            'new_part_name' => 'Bass',
        ])->assertForbidden();

        $this->assertSame(0, SongInstrumentPart::query()->where('song_id', $song->id)->count());
    }

    public function test_adding_part_does_not_remove_existing_parts(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Preserve Parts Song');
        $this->attachPart($song, 'Bass');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->post(route('songs.instrument-parts.store', $song), [
            '_token' => session()->token(),
            'new_part_name' => 'Trumpet',
        ])->assertRedirect();

        $this->assertSame(2, SongInstrumentPart::query()->where('song_id', $song->id)->count());
        $this->assertTrue(
            SongInstrumentPart::query()
                ->where('song_id', $song->id)
                ->whereHas('instrumentPart', fn ($query) => $query->where('name', 'Bass'))
                ->exists(),
        );
    }

    private function seedSong(string $name): Song
    {
        return Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => (string) Str::padLeft((string) random_int(1, 999), 3, '0'),
            'name' => $name,
            'bpm' => 120,
            'time_signature_id' => TimeSignature::query()->where('label', '4/4')->value('id'),
            'musical_key_id' => MusicalKey::query()->where('label', 'G major')->value('id'),
            'mood_id' => SongMood::query()->where('slug', 'happy')->value('id'),
            'status' => 'ready',
        ]);
    }

    private function attachPart(Song $song, string $partName): SongInstrumentPart
    {
        $part = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => $partName,
            'active' => true,
        ]);

        return SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
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

    private function seedPlaylistItem(Show $show, Song $song): ShowPlaylistItem
    {
        return ShowPlaylistItem::query()->create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => 1,
            'is_active' => true,
        ]);
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
