<?php

namespace Tests\Feature;

use App\Enums\BandRole;
use App\Models\Band;
use App\Models\Musician;
use App\Models\MusicianBandRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class BandPeopleManagementTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_director_can_create_person_with_multiple_band_roles(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        $response = $this->actingAs($user)->post(route('people.store'), [
            'first_name' => 'Ed',
            'last_name' => 'Operator',
            'band_roles' => [BandRole::Director->value, BandRole::Musician->value],
        ]);

        $response->assertRedirect(route('people.index'));
        $response->assertSessionHas('status');

        $person = Musician::query()->where('first_name', 'Ed')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [BandRole::Director->value, BandRole::Musician->value],
            $person->bandRoleValues()
        );
    }

    public function test_musician_role_defaults_when_created_without_band_roles(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)->post(route('musicians.store'), [
            'first_name' => 'Default',
            'last_name' => 'Musician',
        ])->assertRedirect(route('people.index'));

        $person = Musician::query()->where('first_name', 'Default')->firstOrFail();
        $this->assertTrue($person->hasBandRole(BandRole::Musician));
    }

    public function test_person_can_be_created_without_login(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)->post(route('people.store'), [
            'first_name' => 'Sam',
            'last_name' => 'Engineer',
            'band_roles' => [BandRole::SoundEngineer->value],
        ])->assertRedirect(route('people.index'));

        $person = Musician::query()->where('first_name', 'Sam')->firstOrFail();
        $this->assertNull($person->user_id);
        $this->assertTrue($person->hasBandRole(BandRole::SoundEngineer));
    }

    public function test_director_can_store_operational_profile_fields_on_edit_only(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $person = Musician::factory()->create(['band_id' => $band->id]);
        MusicianBandRole::create(['musician_id' => $person->id, 'role' => BandRole::TravelManager->value]);

        $this->actingAs($user)->put(route('people.update', $person), [
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'band_roles' => [BandRole::TravelManager->value],
            'dietary_preferences' => 'Vegetarian',
            'allergies' => 'Peanuts',
            'accessibility_notes' => 'Step-free access required',
            'travel_notes' => 'Prefers morning flights',
            'emergency_contact_notes' => 'Partner: Alex 555-0100',
        ])->assertRedirect(route('people.index'));

        $person->refresh();
        $this->assertSame('Vegetarian', $person->dietary_preferences);
        $this->assertSame('Peanuts', $person->allergies);
        $this->assertSame('Step-free access required', $person->accessibility_notes);
    }

    public function test_sensitive_fields_are_not_shown_on_people_index(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $person = Musician::factory()->create([
            'band_id' => $band->id,
            'allergies' => 'Top secret allergy info',
        ]);
        MusicianBandRole::create(['musician_id' => $person->id, 'role' => BandRole::Musician->value]);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee($person->display_name)
            ->assertDontSee('Top secret allergy info');
    }

    public function test_legacy_musician_routes_still_work(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)->post(route('musicians.store'), [
            'first_name' => 'Legacy',
            'last_name' => 'Route',
        ])->assertRedirect(route('people.index'));

        $this->actingAs($user)
            ->get(route('musicians.index'))
            ->assertOk()
            ->assertSee('Legacy Route')
            ->assertSee('Musician');
    }

    public function test_primary_director_can_be_assigned_for_handover_prep(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $person = Musician::factory()->create(['band_id' => $band->id]);
        MusicianBandRole::create(['musician_id' => $person->id, 'role' => BandRole::Director->value]);

        $this->actingAs($user)->put(route('people.update', $person), [
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'band_roles' => [BandRole::Director->value],
            'is_primary_director' => '1',
        ])->assertRedirect();

        $this->assertSame($person->id, $band->fresh()->primary_director_musician_id);
    }
}
