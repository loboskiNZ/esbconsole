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
            'portal.band_asset_disk' => 'local',
        ]);

        Storage::fake('local');
        $this->ensurePortalBand();
        $this->seedBandProfile();
    }

    public function test_director_can_access_manage_band_page(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio/band')
            ->assertOk()
            ->assertSee('Manage Band', false)
            ->assertSee('Ed and the Shadow Boys', false)
            ->assertSee('High-energy ska and Latin grooves.', false)
            ->assertSee('Ska', false);
    }

    public function test_musician_cannot_access_manage_band_page(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)->get('/studio/band')->assertForbidden();
    }

    public function test_current_band_data_loads_on_page(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio/band')
            ->assertOk()
            ->assertSee('value="Ed and the Shadow Boys"', false)
            ->assertSee('High-energy ska and Latin grooves.', false)
            ->assertSee('Ska', false)
            ->assertSee('Latin', false);
    }

    public function test_band_profile_updates_persist(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', [
                '_token' => session()->token(),
                'name' => 'Ed and the Shadow Boys (Tour)',
                'bio' => 'Updated band bio.',
                'styles' => "Rock\nReggae",
            ])
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertSame('Ed and the Shadow Boys (Tour)', $band->name);
        $this->assertSame('Updated band bio.', $band->bio);
        $this->assertSame(['Rock', 'Reggae'], $band->styles);
    }

    public function test_logo_upload_works(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', [
                '_token' => session()->token(),
                'name' => 'Ed and the Shadow Boys',
                'bio' => 'High-energy ska and Latin grooves.',
                'styles' => "Ska\nLatin",
                'logo' => UploadedFile::fake()->image('band-logo.png', 400, 200),
            ])
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertNotNull($band->logo_path);
        Storage::disk('local')->assertExists($band->logo_path);

        $this->actingAs($director)->get(route('studio.band.logo'))->assertOk();
    }

    public function test_photo_upload_works(): void
    {
        $director = $this->createDirectorUser();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', [
                '_token' => session()->token(),
                'name' => 'Ed and the Shadow Boys',
                'bio' => 'High-energy ska and Latin grooves.',
                'styles' => "Ska\nLatin",
                'photo' => UploadedFile::fake()->image('band-photo.jpg', 1200, 800),
            ])
            ->assertRedirect(route('studio.band.edit'));

        $band = Band::query()->findOrFail(1);

        $this->assertNotNull($band->photo_path);
        Storage::disk('local')->assertExists($band->photo_path);

        $this->actingAs($director)->get(route('studio.band.photo'))->assertOk();
    }

    public function test_old_logo_file_is_preserved_when_replaced(): void
    {
        $director = $this->createDirectorUser();
        $oldPath = 'portal/band-assets/1/logo-legacy.png';

        Storage::disk('local')->put($oldPath, 'legacy-logo-bytes');

        Band::query()->whereKey(1)->update(['logo_path' => $oldPath]);

        $this->beginBandManagementSession($director)
            ->put('/studio/band', [
                '_token' => session()->token(),
                'name' => 'Ed and the Shadow Boys',
                'bio' => 'High-energy ska and Latin grooves.',
                'styles' => "Ska\nLatin",
                'logo' => UploadedFile::fake()->image('band-logo-new.png', 300, 150),
            ])
            ->assertRedirect(route('studio.band.edit'));

        Storage::disk('local')->assertExists($oldPath);

        $newPath = Band::query()->findOrFail(1)->logo_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_unrelated_band_data_is_preserved_on_update(): void
    {
        $director = $this->createDirectorUser();

        Band::query()->whereKey(1)->update([
            'public_id' => 'bef29738-65b0-443f-ac49-3406c5eba501',
        ]);

        $before = DB::table('bands')->where('id', 1)->first();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', [
                '_token' => session()->token(),
                'name' => 'Ed and the Shadow Boys',
                'bio' => 'Only bio changed.',
                'styles' => 'Ska',
            ])
            ->assertRedirect(route('studio.band.edit'));

        $after = DB::table('bands')->where('id', 1)->first();

        $this->assertSame($before->public_id, $after->public_id);
        $this->assertSame($before->primary_director_musician_id, $after->primary_director_musician_id);
        $this->assertSame($before->created_at, $after->created_at);
        $this->assertSame('Only bio changed.', $after->bio);
    }

    public function test_band_management_never_deletes_band_rows(): void
    {
        $director = $this->createDirectorUser();
        $bandsBefore = DB::table('bands')->count();

        $this->beginBandManagementSession($director)
            ->put('/studio/band', [
                '_token' => session()->token(),
                'name' => 'Ed and the Shadow Boys',
                'bio' => 'Updated again.',
                'styles' => 'Latin',
                'photo' => UploadedFile::fake()->image('another-photo.jpg'),
            ])
            ->assertRedirect(route('studio.band.edit'));

        $this->assertSame($bandsBefore, DB::table('bands')->count());
        $this->assertDatabaseHas('bands', ['id' => 1, 'name' => 'Ed and the Shadow Boys']);
    }

    public function test_director_sees_manage_band_link_on_studio_dashboard(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Manage Band', false)
            ->assertSee(route('studio.band.edit'), false);
    }

    private function seedBandProfile(): void
    {
        if (! DB::table('bands')->where('id', 1)->exists()) {
            $this->ensurePortalBand();
        }

        Band::query()->whereKey(1)->update([
            'bio' => 'High-energy ska and Latin grooves.',
            'styles' => ['Ska', 'Latin'],
        ]);
    }

    private function beginBandManagementSession(User $director): self
    {
        $this->actingAs($director)->get('/studio/band')->assertOk();

        return $this;
    }
}
