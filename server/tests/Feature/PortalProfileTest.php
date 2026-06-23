<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_my_profile_card(): void
    {
        $user = User::factory()->create();
        $person = $user->person;
        $vocals = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $keys = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();

        $person->instruments()->attach($vocals->id, ['is_primary' => true]);
        $person->instruments()->attach($keys->id, ['is_primary' => false]);

        $response = $this->actingAs($user)->get('/studio');

        $response->assertOk();
        $response->assertSee('My Profile', false);
        $response->assertSee($person->artistic_name, false);
        $response->assertSee($person->legalName(), false);
        $response->assertSee($person->email, false);
        $response->assertSee('Vocals', false);
        $response->assertSee('Keys', false);
        $response->assertDontSee('Readiness score', false);
        $response->assertDontSee('Profile completeness', false);
        $response->assertDontSee('Performance readiness', false);
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
            'primary_instrument' => 'scaffold-drums',
            'additional_instruments' => ['scaffold-percussion'],
        ]);

        $response->assertRedirect(route('studio'));
        $person->refresh()->load('instruments');

        $this->assertSame('Updated Stage', $person->artistic_name);
        $this->assertSame('updated@example.com', $person->email);
        $this->assertSame('Christchurch', $person->city);
        $this->assertSame('scaffold-drums', $person->primaryInstrument()?->slug);
        $this->assertCount(2, $person->instruments);
        $this->assertSame(1, $person->instruments->where(fn ($i) => $i->pivot->is_primary)->count());
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
