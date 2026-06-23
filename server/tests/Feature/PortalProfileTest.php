<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\User;
use App\Support\ProfileBio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_compact_identity_card(): void
    {
        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $keys = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();

        $person->update([
            'artistic_name' => 'Shadow Singer',
            'country' => 'New Zealand',
        ]);
        $person->instruments()->attach($vocals->id, ['is_primary' => true]);
        $person->instruments()->attach($keys->id, ['is_primary' => false]);

        $response = $this->actingAs($user)->get('/studio');

        $response->assertOk();
        $response->assertSee('My Profile', false);
        $response->assertSee('Shadow Singer', false);
        $response->assertSee('Vocals', false);
        $response->assertSee('Keys', false);
        $response->assertSee('New Zealand', false);
        $response->assertSee('Edit', false);
        $response->assertSee('esb-studio__identity-widget', false);
        $response->assertSee('esb-studio__shell', false);
        $response->assertSee('esb-studio__layout', false);
        $response->assertSee('esb-studio__workspace', false);
        $response->assertSee('esb-studio__sidebar', false);
        $response->assertSee('Studio modules', false);
        $response->assertDontSee('max-w-6xl', false);
        $response->assertDontSee($person->legalName(), false);
        $response->assertDontSee($person->email, false);
        $response->assertDontSee($person->phone, false);
        $response->assertDontSee('Readiness score', false);
        $response->assertDontSee('Profile completeness', false);
        $response->assertDontSee('Performance readiness', false);
    }

    public function test_studio_uses_full_viewport_workspace_shell(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/studio');

        $response->assertOk();
        $response->assertSee('esb-studio__shell', false);
        $response->assertSee('esb-studio__chrome-header', false);
        $response->assertSee('esb-studio__shell-body', false);
        $response->assertSee('esb-studio__chrome-footer', false);
        $response->assertDontSee('max-w-6xl', false);
        $response->assertDontSee('max-w-5xl', false);
        $response->assertDontSee('mx-auto w-full max-w', false);
    }

    public function test_studio_workspace_layout_is_primary_content_area(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/studio');

        $response->assertOk();
        $response->assertSeeInOrder([
            'esb-studio__sidebar',
            'esb-studio__workspace',
        ], false);
        $response->assertSee('esb-studio__workspace-intro', false);
        $response->assertSee('esb-studio__workspace-grid', false);
        $response->assertSee('Welcome', false);
        $response->assertSee('Information for later', false);
    }

    public function test_studio_mobile_layout_stacks_profile_before_workspace(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/studio')->getContent();
        $sidebarPos = strpos($html, 'esb-studio__sidebar');
        $workspacePos = strpos($html, 'esb-studio__workspace');

        $this->assertNotFalse($sidebarPos);
        $this->assertNotFalse($workspacePos);
        $this->assertLessThan($workspacePos, $sidebarPos);
    }

    public function test_no_photo_placeholder_uses_esb_branding_and_no_image_label(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/studio');

        $response->assertOk();
        $response->assertSee('No image', false);
        $response->assertSee('esb-studio__identity-placeholder--no-image', false);
        $response->assertSee('Logo_ESB_BLACKBG.png', false);
        $response->assertSee('esb-studio__identity-placeholder-figure', false);
    }

    public function test_profile_editor_uses_onboarding_instrument_markup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio/profile/edit')
            ->assertOk()
            ->assertSee('esb-onboarding__instrument-chip', false)
            ->assertSee('esb-onboarding__instrument-grid', false)
            ->assertSee('setPrimaryWeapon', false)
            ->assertSee('toggleAdditionalWeapon', false)
            ->assertSee('Show instruments', false);
    }

    public function test_unauthenticated_user_cannot_access_profile_editor(): void
    {
        $this->get('/studio/profile/edit')->assertRedirect('/');
    }

    public function test_authenticated_user_can_edit_own_profile(): void
    {
        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $person->instruments()->attach($vocals->id, ['is_primary' => true]);

        $response = $this->actingAs($user)->put('/studio/profile', [
            'stage_name' => 'Updated Stage',
            'email' => 'updated@example.com',
            'telephone' => '+64 21 111 2222',
            'city' => 'Christchurch',
            'country' => 'New Zealand',
            'bio' => 'Playing shadows since day one.',
            'primary_instrument' => 'scaffold-drums',
            'additional_instruments' => ['scaffold-percussion'],
        ]);

        $response->assertRedirect(route('studio'));
        $person->refresh()->load('instruments');

        $this->assertSame('Updated Stage', $person->artistic_name);
        $this->assertSame('updated@example.com', $person->email);
        $this->assertSame('Christchurch', $person->city);
        $this->assertSame('Playing shadows since day one.', $person->bio);
        $this->assertSame('scaffold-drums', $person->primaryInstrument()?->slug);
        $this->assertCount(2, $person->instruments);
        $this->assertSame(1, $person->instruments->where(fn ($i) => $i->pivot->is_primary)->count());
    }

    public function test_bio_over_one_hundred_words_is_rejected(): void
    {
        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $person->instruments()->attach($vocals->id, ['is_primary' => true]);

        $bio = implode(' ', array_fill(0, ProfileBio::MAX_WORDS + 1, 'word'));

        $response = $this->actingAs($user)->put('/studio/profile', [
            'stage_name' => $person->artistic_name,
            'email' => $person->email,
            'telephone' => $person->phone,
            'city' => $person->city,
            'country' => $person->country,
            'bio' => $bio,
            'primary_instrument' => 'scaffold-vocals',
        ]);

        $response->assertSessionHasErrors('bio');
        $this->assertNull($person->fresh()->bio);
    }

    public function test_user_can_upload_profile_photo_with_original_and_display_paths(): void
    {
        Storage::fake('local');
        config(['portal.profile_photo_disk' => 'local']);

        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $person->instruments()->attach($vocals->id, ['is_primary' => true]);

        $file = UploadedFile::fake()->image('profile.jpg', 400, 400);

        $this->actingAs($user)->put('/studio/profile', [
            'stage_name' => $person->artistic_name,
            'email' => $person->email,
            'telephone' => $person->phone,
            'city' => $person->city,
            'country' => $person->country,
            'primary_instrument' => 'scaffold-vocals',
            'profile_photo' => $file,
        ])->assertRedirect(route('studio'));

        $person->refresh();
        $this->assertStringEndsWith('/original.jpg', $person->profile_photo_path);
        $this->assertStringEndsWith('/display.jpg', $person->profile_photo_display_path);
        Storage::disk('local')->assertExists($person->profile_photo_path);
        Storage::disk('local')->assertExists($person->profile_photo_display_path);

        $this->actingAs($user)->get(route('studio.profile.photo'))->assertOk();
    }

    public function test_twenty_megabyte_profile_photo_upload_is_accepted(): void
    {
        Storage::fake('local');
        config(['portal.profile_photo_disk' => 'local']);

        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $person->instruments()->attach($vocals->id, ['is_primary' => true]);

        $file = UploadedFile::fake()->image('band-photo.jpg', 1200, 1200)->size(20 * 1024);

        $this->actingAs($user)->put('/studio/profile', [
            'stage_name' => $person->artistic_name,
            'email' => $person->email,
            'telephone' => $person->phone,
            'city' => $person->city,
            'country' => $person->country,
            'primary_instrument' => 'scaffold-vocals',
            'profile_photo' => $file,
        ])->assertRedirect(route('studio'));

        $person->refresh();
        $this->assertNotNull($person->profile_photo_path);
        $this->assertNotNull($person->profile_photo_display_path);
    }

    public function test_invalid_profile_photo_format_is_rejected(): void
    {
        Storage::fake('local');
        config(['portal.profile_photo_disk' => 'local']);

        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $person->instruments()->attach($vocals->id, ['is_primary' => true]);

        $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

        $this->actingAs($user)->put('/studio/profile', [
            'stage_name' => $person->artistic_name,
            'email' => $person->email,
            'telephone' => $person->phone,
            'city' => $person->city,
            'country' => $person->country,
            'primary_instrument' => 'scaffold-vocals',
            'profile_photo' => $file,
        ])->assertSessionHasErrors('profile_photo');

        $this->assertNull($person->fresh()->profile_photo_path);
    }

    public function test_profile_update_only_affects_authenticated_users_person(): void
    {
        $firstUser = User::factory()->create(['username' => 'profileone']);
        $secondUser = User::factory()->create(['username' => 'profiletwo']);
        $secondPerson = $secondUser->person;

        $this->actingAs($firstUser)->put('/studio/profile', [
            'stage_name' => 'Wrong Target',
            'email' => 'wrong@example.com',
            'telephone' => '+64 21 000 0001',
            'city' => 'Dunedin',
            'country' => 'New Zealand',
            'primary_instrument' => 'scaffold-vocals',
        ])->assertRedirect(route('studio'));

        $secondPerson->refresh();

        $this->assertNotSame('Wrong Target', $secondPerson->artistic_name);
        $this->assertNotSame('wrong@example.com', $secondPerson->email);
    }

    public function test_user_without_person_cannot_access_profile_editor(): void
    {
        $user = User::factory()->create(['person_id' => null]);

        $this->actingAs($user)->get('/studio/profile/edit')->assertForbidden();
        $this->actingAs($user)->put('/studio/profile', [
            'stage_name' => 'Blocked',
            'email' => 'blocked@example.com',
            'telephone' => '+64 21 000 0002',
            'city' => 'Dunedin',
            'country' => 'New Zealand',
            'primary_instrument' => 'scaffold-vocals',
        ])->assertForbidden();
    }

    public function test_studio_still_requires_authentication(): void
    {
        $this->get('/studio')->assertRedirect('/');
    }

    public function test_no_profile_seed_data_added(): void
    {
        $this->assertFileDoesNotExist(database_path('seeders/PortalProfileSeeder.php'));
    }
}
