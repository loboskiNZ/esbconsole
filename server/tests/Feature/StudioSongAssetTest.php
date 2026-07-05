<?php

namespace Tests\Feature;

use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\User;
use App\Services\StudioShowService;
use App\Support\SongAssetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioSongAssetTest extends TestCase
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

    public function test_spotify_url_saves_on_song_update(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Spotify Song');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => $song->name,
            'spotify_url' => 'https://open.spotify.com/track/abc123',
        ])->assertRedirect();

        $this->assertSame(
            'https://open.spotify.com/track/abc123',
            Song::query()->find($song->id)?->spotify_url,
        );
    }

    public function test_youtube_url_saves_on_song_update(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('YouTube Song');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => $song->name,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertRedirect();

        $this->assertSame(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            Song::query()->find($song->id)?->youtube_url,
        );
    }

    public function test_mp3_upload_stores_on_media_disk(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('MP3 Song');

        $this->uploadAsset($director, $song, UploadedFile::fake()->create('electronic-love.mp3', 120, 'audio/mpeg'), 'audio');

        $asset = SongAsset::query()->where('song_id', $song->id)->firstOrFail();
        $this->assertStringStartsWith('library/songs/'.$song->id.'/assets/audio/', $asset->storage_reference);
        Storage::disk('media')->assertExists($asset->storage_reference);
        Storage::disk('library')->assertMissing($asset->storage_reference);
        Storage::disk('local')->assertMissing($asset->storage_reference);
    }

    public function test_wav_upload_stores_on_media_disk(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('WAV Song');

        $this->uploadAsset($director, $song, UploadedFile::fake()->create('stem.wav', 200, 'audio/wav'), 'stem');

        $asset = SongAsset::query()->where('song_id', $song->id)->firstOrFail();
        Storage::disk('media')->assertExists($asset->storage_reference);
    }

    public function test_midi_upload_stores_on_media_disk(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('MIDI Song');

        $this->uploadAsset($director, $song, UploadedFile::fake()->create('electronic-love.mid', 50, 'audio/midi'), 'midi');

        $asset = SongAsset::query()->where('song_id', $song->id)->firstOrFail();
        Storage::disk('media')->assertExists($asset->storage_reference);
    }

    public function test_multiple_files_can_be_attached_to_one_song(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Multi Asset Song');

        $this->uploadAsset($director, $song, UploadedFile::fake()->create('full.mp3', 100, 'audio/mpeg'), 'audio', 'Full mix');
        $this->uploadAsset($director, $song, UploadedFile::fake()->create('no-drums.mp3', 100, 'audio/mpeg'), 'backing_track', 'No drums');
        $this->uploadAsset($director, $song, UploadedFile::fake()->create('horns.mid', 40, 'audio/midi'), 'midi', 'Horns MIDI');

        $assets = SongAsset::query()->where('song_id', $song->id)->orderBy('sort_order')->get();
        $this->assertCount(3, $assets);
        $this->assertSame(['Full mix', 'No drums', 'Horns MIDI'], $assets->pluck('label')->all());
    }

    public function test_existing_song_metadata_update_still_works(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Metadata Song');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => 'Renamed Metadata Song',
            'bpm' => 128,
        ])->assertRedirect();

        $song->refresh();
        $this->assertSame('Renamed Metadata Song', $song->name);
        $this->assertSame(128, $song->bpm);
    }

    public function test_return_to_flow_from_show_playlist_still_works(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Asset Return Show']);
        $song = $this->seedSong('Asset Return Song');
        $this->seedPlaylistItem($show, $song);
        $returnTo = '/studio/shows/'.$show->id.'#playlist';

        $this->actingAs($director)->get(route('songs.edit', ['song' => $song, 'return_to' => $returnTo]));

        $this->actingAs($director)->post(route('songs.assets.store', $song), [
            '_token' => session()->token(),
            'file' => UploadedFile::fake()->create('return.mp3', 80, 'audio/mpeg'),
            'asset_type' => SongAssetType::AUDIO,
            'label' => 'Return mix',
            'return_to' => $returnTo,
        ])->assertRedirect($returnTo);
    }

    public function test_song_asset_download_route_works(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Download Song');

        $this->uploadAsset($director, $song, UploadedFile::fake()->create('download.mp3', 80, 'audio/mpeg'), 'audio');
        $asset = SongAsset::query()->where('song_id', $song->id)->firstOrFail();

        Storage::disk('media')->put($asset->storage_reference, 'fake-audio-bytes');

        $this->actingAs($director)->get(route('songs.assets.file', [$song, $asset]))
            ->assertOk();
    }

    public function test_musician_can_download_song_asset_file(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Musician Download Song');

        $this->uploadAsset($director, $song, UploadedFile::fake()->create('musician.mp3', 80, 'audio/mpeg'), 'audio');
        $asset = SongAsset::query()->where('song_id', $song->id)->firstOrFail();

        Storage::disk('media')->put($asset->storage_reference, 'fake-audio-bytes');

        $this->actingAs($musician)->get(route('songs.assets.file', [$song, $asset]))
            ->assertOk();
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Invalid Type Song');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->post(route('songs.assets.store', $song), [
            '_token' => session()->token(),
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'asset_type' => SongAssetType::OTHER,
            'label' => 'Bad file',
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, SongAsset::query()->where('song_id', $song->id)->count());
    }

    private function uploadAsset(
        \App\Models\User $director,
        Song $song,
        UploadedFile $file,
        string $assetType,
        string $label = 'Test asset',
    ): void {
        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->post(route('songs.assets.store', $song), [
            '_token' => session()->token(),
            'file' => $file,
            'asset_type' => $assetType,
            'label' => $label,
        ])->assertRedirect();
    }

    private function seedSong(string $name): Song
    {
        return Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => (string) Str::padLeft((string) random_int(1, 999), 3, '0'),
            'name' => $name,
            'bpm' => 120,
            'time_signature_id' => \App\Models\Library\TimeSignature::query()->where('label', '4/4')->value('id'),
            'musical_key_id' => \App\Models\Library\MusicalKey::query()->where('label', 'G major')->value('id'),
            'mood_id' => \App\Models\Library\SongMood::query()->where('slug', 'happy')->value('id'),
            'status' => 'ready',
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
        if (\App\Models\Library\SongMood::query()->exists()) {
            return;
        }

        \App\Models\Library\SongMood::query()->create([
            'name' => 'Happy',
            'slug' => 'happy',
            'colour_hex' => '#FFB23E',
            'accent_colour_hex' => '#FFD27A',
            'sort_order' => 20,
            'active' => true,
        ]);

        \App\Models\Library\TimeSignature::query()->create(['label' => '4/4', 'sort_order' => 10, 'active' => true]);
        \App\Models\Library\MusicalKey::query()->create(['label' => 'G major', 'tonic' => 'G', 'mode' => 'major', 'sort_order' => 10, 'active' => true]);
    }
}
