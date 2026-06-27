<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioBandProfileTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal.band_id' => 1,
        ]);

        Storage::fake('media');
        Storage::fake('local');
        $this->ensurePortalBand();
        $this->seedBandProfile();
    }

    public function test_director_can_view_all_expanded_band_fields(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio/band')
            ->assertOk()
            ->assertSee('Manage Band', false)
            ->assertSee('Identity', false)
            ->assertSee('Biography', false)
            ->assertSee('Contact', false)
            ->assertSee('Social links', false)
            ->assertSee('Media', false)
            ->assertSee('Ed and the Shadow Boys', false)
            ->assertSee('ESB', false)
            ->assertSee('Dunedin ska powerhouse.', false)
            ->assertSee('booking@example.com', false)
            ->assertSee('https://facebook.com/esb', false)
            ->assertSee('Ska', false);
    }

    public function test_musician_cannot_access_manage_band_page(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)->get('/studio/band')->assertForbidden();
    }

    public function test_director_can_update_identity_fields(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'name' => 'Ed and the Shadow Boys (Tour)',
                'short_name' => 'ESB Tour',
                'tagline' => 'Live on the road.',
                'hometown' => 'Christchurch',
                'formation_year' => 2018,
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertSame('Ed and the Shadow Boys (Tour)', $band->name);
        $this->assertSame('ESB Tour', $band->short_name);
        $this->assertSame('Live on the road.', $band->tagline);
        $this->assertSame('Christchurch', $band->hometown);
        $this->assertSame(2018, $band->formation_year);
    }

    public function test_director_can_update_biography_fields(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'short_bio' => 'Short promo copy.',
                'full_bio' => 'Long-form band history and story.',
                'styles' => "Rock\nReggae",
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertSame('Short promo copy.', $band->short_bio);
        $this->assertSame('Long-form band history and story.', $band->full_bio);
        $this->assertSame(['Rock', 'Reggae'], $band->styles);
    }

    public function test_director_can_update_contact_fields(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'booking_email' => 'agent@example.com',
                'booking_phone' => '+64 21 555 0101',
                'website_url' => 'https://edandtheshadowboys.example',
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertSame('agent@example.com', $band->booking_email);
        $this->assertSame('+64 21 555 0101', $band->booking_phone);
        $this->assertSame('https://edandtheshadowboys.example', $band->website_url);
    }

    public function test_director_can_update_social_links(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'facebook_url' => 'https://facebook.com/esb-new',
                'instagram_url' => 'https://instagram.com/esb-new',
                'tiktok_url' => 'https://tiktok.com/@esb-new',
                'youtube_url' => 'https://youtube.com/@esb-new',
                'spotify_url' => 'https://open.spotify.com/artist/esb-new',
                'apple_music_url' => 'https://music.apple.com/artist/esb-new',
                'bandcamp_url' => 'https://esb.bandcamp.com',
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertSame('https://facebook.com/esb-new', $band->facebook_url);
        $this->assertSame('https://instagram.com/esb-new', $band->instagram_url);
        $this->assertSame('https://tiktok.com/@esb-new', $band->tiktok_url);
        $this->assertSame('https://youtube.com/@esb-new', $band->youtube_url);
        $this->assertSame('https://open.spotify.com/artist/esb-new', $band->spotify_url);
        $this->assertSame('https://music.apple.com/artist/esb-new', $band->apple_music_url);
        $this->assertSame('https://esb.bandcamp.com', $band->bandcamp_url);
    }

    public function test_logo_upload_works(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'logo' => UploadedFile::fake()->image('band-logo.png', 400, 200),
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertNotNull($band->logo_path);
        Storage::disk('media')->assertExists($band->logo_path);
        $this->actingAs($director)->get(route('studio.band.logo'))->assertOk();
    }

    public function test_photo_upload_works(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'photo' => UploadedFile::fake()->image('band-photo.jpg', 1200, 800),
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertNotNull($band->photo_path);
        Storage::disk('media')->assertExists($band->photo_path);
        $this->actingAs($director)->get(route('studio.band.photo'))->assertOk();
    }

    public function test_hero_photo_upload_works(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'hero_photo' => UploadedFile::fake()->image('hero-photo.jpg', 1600, 900),
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertNotNull($band->hero_photo_path);
        Storage::disk('media')->assertExists($band->hero_photo_path);
        $this->actingAs($director)->get(route('studio.band.hero'))->assertOk();
    }

    public function test_press_photo_upload_works(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'press_photo' => UploadedFile::fake()->image('press-photo.jpg', 1200, 1600),
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertNotNull($band->press_photo_path);
        Storage::disk('media')->assertExists($band->press_photo_path);
        $this->actingAs($director)->get(route('studio.band.press'))->assertOk();
    }

    public function test_old_logo_file_is_preserved_when_replaced(): void
    {
        $director = $this->createDirectorUser();
        $oldPath = 'portal/band-assets/1/logo-legacy.png';

        Storage::disk('local')->put($oldPath, 'legacy-logo-bytes');
        Band::query()->whereKey(1)->update(['logo_path' => $oldPath]);

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'logo' => UploadedFile::fake()->image('band-logo-new.png', 300, 150),
            ]))
            ->assertRedirect(route('studio.band.edit'));

        Storage::disk('local')->assertExists($oldPath);

        $newPath = Band::query()->findOrFail(1)->logo_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('media')->assertExists($newPath);
    }

    public function test_old_photo_file_is_preserved_when_replaced(): void
    {
        $director = $this->createDirectorUser();
        $oldPath = 'portal/band-assets/1/photo-legacy.jpg';

        Storage::disk('local')->put($oldPath, 'legacy-photo-bytes');
        Band::query()->whereKey(1)->update(['photo_path' => $oldPath]);

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'photo' => UploadedFile::fake()->image('band-photo-new.jpg', 900, 600),
            ]))
            ->assertRedirect(route('studio.band.edit'));

        Storage::disk('local')->assertExists($oldPath);
        $this->assertNotSame($oldPath, Band::query()->findOrFail(1)->photo_path);
    }

    public function test_unrelated_band_data_is_preserved_on_update(): void
    {
        $director = $this->createDirectorUser();

        Band::query()->whereKey(1)->update([
            'public_id' => 'bef29738-65b0-443f-ac49-3406c5eba501',
            'bio' => 'Legacy bio column value.',
        ]);

        $before = DB::table('bands')->where('id', 1)->first();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'short_bio' => 'Only short bio changed.',
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $after = DB::table('bands')->where('id', 1)->first();

        $this->assertSame($before->public_id, $after->public_id);
        $this->assertSame($before->primary_director_musician_id, $after->primary_director_musician_id);
        $this->assertSame($before->created_at, $after->created_at);
        $this->assertSame('Legacy bio column value.', $after->bio);
        $this->assertSame('Only short bio changed.', $after->short_bio);
    }

    public function test_band_management_never_deletes_band_rows(): void
    {
        $director = $this->createDirectorUser();
        $bandsBefore = DB::table('bands')->count();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'short_bio' => 'Updated again.',
                'hero_photo' => UploadedFile::fake()->image('another-hero.jpg'),
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $this->assertSame($bandsBefore, DB::table('bands')->count());
        $this->assertDatabaseHas('bands', ['id' => 1, 'name' => 'Ed and the Shadow Boys']);
    }

    public function test_band_profile_data_persists_after_refresh(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', array_merge($this->basePayload(), [
                '_token' => session()->token(),
                'tagline' => 'Persisted tagline.',
            ]))
            ->assertRedirect(route('studio.band.edit'));

        $this->actingAs($director)->get('/studio/band')
            ->assertOk()
            ->assertSee('Persisted tagline.', false);

        $this->assertSame('Persisted tagline.', Band::query()->findOrFail(1)->tagline);
    }

    public function test_director_sees_manage_band_link_on_studio_dashboard(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Manage Band', false)
            ->assertSee(route('studio.band.edit'), false);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'name' => 'Ed and the Shadow Boys',
            'short_name' => 'ESB',
            'tagline' => 'Dunedin ska powerhouse.',
            'hometown' => 'Dunedin',
            'formation_year' => 2015,
            'short_bio' => 'High-energy ska and Latin grooves.',
            'full_bio' => 'Ed and the Shadow Boys bring horn-driven ska to stages across Aotearoa.',
            'styles' => "Ska\nLatin",
            'booking_email' => 'booking@example.com',
            'booking_phone' => '+64 21 000 0000',
            'website_url' => 'https://edandtheshadowboys.example',
            'facebook_url' => 'https://facebook.com/esb',
            'instagram_url' => 'https://instagram.com/esb',
            'tiktok_url' => 'https://tiktok.com/@esb',
            'youtube_url' => 'https://youtube.com/@esb',
            'spotify_url' => 'https://open.spotify.com/artist/esb',
            'apple_music_url' => 'https://music.apple.com/artist/esb',
            'bandcamp_url' => 'https://esb.bandcamp.com',
        ];
    }

    private function seedBandProfile(): void
    {
        if (! DB::table('bands')->where('id', 1)->exists()) {
            $this->ensurePortalBand();
        }

        Band::query()->whereKey(1)->update($this->basePayload());
    }

    private function beginBandManagementSession(User $director): self
    {
        $this->actingAs($director)->get('/studio/band')->assertOk();

        return $this;
    }
}
