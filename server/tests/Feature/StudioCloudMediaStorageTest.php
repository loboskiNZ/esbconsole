<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\InstrumentReference;
use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Models\Library\MusicalKey;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\User;
use App\Services\StudioShowService;
use App\Support\CloudStudioMediaStorage;
use App\Support\InstrumentCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioCloudMediaStorageTest extends TestCase
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

    public function test_new_chart_upload_writes_to_media_disk(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow();
        $song = $this->seedSong('Media Upload Song');
        $part = $this->attachPart($song, 'Keyboard', withChart: false);
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($director)->get(route('studio.shows.playlist.chart.upload.create', [
            'show' => $show,
            'song' => $song,
            'songInstrumentPart' => $part,
        ]))->assertOk();

        $this->actingAs($director)->post(route('studio.shows.playlist.chart.upload.store', [
            'show' => $show,
            'song' => $song,
            'songInstrumentPart' => $part,
        ]), [
            '_token' => session()->token(),
            'chart' => UploadedFile::fake()->create('keyboard.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $part->refresh();
        $chart = Chart::query()->findOrFail($part->chart_id);

        $this->assertStringStartsWith('library/charts/1/', $chart->storage_reference);
        Storage::disk('media')->assertExists($chart->storage_reference);
        Storage::disk('library')->assertMissing(
            app(CloudStudioMediaStorage::class)->legacyLocalRelativePath($chart->storage_reference),
        );
    }

    public function test_legacy_local_chart_still_opens(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Legacy Chart Song');
        $part = $this->attachPart($song, 'Trumpet', withChart: true, storageReference: 'charts/1/010/trumpet.pdf');
        $chart = Chart::query()->findOrFail($part->chart_id);

        Storage::disk('library')->put('charts/1/010/trumpet.pdf', '%PDF-1.4 legacy chart');

        $this->actingAs($director)->get(route('studio.charts.file', $chart))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_new_profile_photo_writes_to_media_disk(): void
    {
        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $person->instruments()->attach($vocals->id, ['is_primary' => true]);

        $this->actingAs($user)->get(route('studio.profile.edit'))->assertOk();

        $this->actingAs($user)->put('/studio/profile', [
            '_token' => session()->token(),
            'stage_name' => $person->artistic_name,
            'email' => $person->email,
            'telephone' => $person->phone,
            'city' => $person->city,
            'country' => $person->country,
            'primary_instrument' => 'scaffold-vocals',
            'profile_photo' => UploadedFile::fake()->image('profile.jpg', 400, 400),
        ])->assertRedirect(route('studio'));

        $person->refresh();
        Storage::disk('media')->assertExists($person->profile_photo_path);
        Storage::disk('media')->assertExists($person->profile_photo_display_path);
        Storage::disk('local')->assertMissing($person->profile_photo_path);

        $this->actingAs($user)->get(route('studio.profile.photo'))->assertOk();
    }

    public function test_legacy_local_profile_photo_still_opens(): void
    {
        $user = User::factory()->create();
        $path = 'portal/profile-photos/'.$user->person_id.'/display.jpg';
        $user->person->update(['profile_photo_display_path' => $path]);

        Storage::disk('local')->put($path, 'legacy-profile-display-bytes');

        $this->actingAs($user)->get(route('studio.profile.photo'))->assertOk();
    }

    public function test_new_band_asset_writes_to_media_disk(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio/band')->assertOk();

        $this->actingAs($director)->put('/studio/band', [
            '_token' => session()->token(),
            'name' => 'Ed and the Shadow Boys',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);
        $this->assertNotNull($band->logo_path);
        Storage::disk('media')->assertExists($band->logo_path);
        Storage::disk('local')->assertMissing($band->logo_path);

        $this->actingAs($director)->get(route('studio.band.logo'))->assertOk();
    }

    public function test_legacy_local_band_asset_still_opens(): void
    {
        $director = $this->createDirectorUser();
        $path = 'portal/band-assets/1/logo-legacy.png';
        Band::query()->findOrFail(1)->update(['logo_path' => $path]);
        Storage::disk('local')->put($path, 'legacy-logo-bytes');

        $this->actingAs($director)->get(route('studio.band.logo'))->assertOk();
    }

    public function test_missing_chart_file_returns_not_found_not_server_error(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Missing Media Song');
        $part = $this->attachPart($song, 'Trumpet', withChart: true, storageReference: 'library/charts/1/011/missing.pdf');
        $chart = Chart::query()->findOrFail($part->chart_id);

        $this->actingAs($director)->get(route('studio.charts.file', $chart))->assertNotFound();
    }

    private function seedShow(): Show
    {
        return app(StudioShowService::class)->createShow([
            'name' => 'Media Test Show',
            'lifecycle_state' => Show::STATE_DRAFT,
        ]);
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

    private function attachPart(
        Song $song,
        string $partName,
        bool $withChart,
        ?string $storageReference = null,
    ): SongInstrumentPart {
        $part = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => $partName,
            'active' => true,
        ]);

        $chartId = null;
        if ($withChart) {
            $reference = $storageReference ?? 'charts/1/'.$song->song_code.'/'.Str::slug($partName).'.pdf';
            $chart = Chart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'title' => $partName.' Chart',
                'original_filename' => Str::slug($partName).'.pdf',
                'storage_reference' => $reference,
                'checksum' => hash('sha256', $partName),
                'mime_type' => 'application/pdf',
                'file_size' => 100,
            ]);
            $chartId = $chart->id;
        }

        return SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
            'chart_id' => $chartId,
        ]);
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

        if (! InstrumentReference::query()->where('slug', 'scaffold-vocals')->exists()) {
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
