<?php

namespace Tests\Feature;

use App\Enums\BandRole;
use App\Models\Band;
use App\Models\Musician;
use App\Models\User;
use Database\Seeders\BandSeeder;
use Database\Seeders\DirectorUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class DirectorBandPersonSyncTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_director_seeder_creates_linked_band_person_with_director_role(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(BandSeeder::class);
        $this->seed(DirectorUserSeeder::class);

        $user = User::query()->where('email', DirectorUserSeeder::DIRECTOR_EMAIL)->firstOrFail();
        $person = Musician::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(DirectorUserSeeder::DIRECTOR_EMAIL, $person->email);
        $this->assertTrue($person->hasBandRole(BandRole::Director));
        $this->assertTrue($person->active);
    }

    public function test_director_person_appears_on_people_index(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(BandSeeder::class);
        $this->seed(DirectorUserSeeder::class);

        $user = User::query()->where('email', DirectorUserSeeder::DIRECTOR_EMAIL)->firstOrFail();

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('Ed')
            ->assertSee('Director');
    }

    public function test_running_director_seeder_twice_does_not_create_duplicate_people(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(BandSeeder::class);
        $this->seed(DirectorUserSeeder::class);
        $this->seed(DirectorUserSeeder::class);

        $user = User::query()->where('email', DirectorUserSeeder::DIRECTOR_EMAIL)->firstOrFail();

        $this->assertSame(1, Musician::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, User::query()->where('email', DirectorUserSeeder::DIRECTOR_EMAIL)->count());
    }

    public function test_active_person_can_be_archived_and_restored(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        $this->actingAs($user)->post(route('people.store'), [
            'first_name' => 'Archive',
            'last_name' => 'Candidate',
            'band_roles' => [BandRole::Musician->value],
        ])->assertRedirect(route('people.index'));

        $person = Musician::query()->where('first_name', 'Archive')->firstOrFail();
        $this->assertTrue($person->active);

        $this->actingAs($user)
            ->post(route('people.archive', $person))
            ->assertRedirect(route('people.index'));

        $person->refresh();
        $this->assertFalse($person->active);
        $this->assertDatabaseHas('musicians', ['id' => $person->id, 'active' => false]);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('Archived Band People')
            ->assertSee('Archive Candidate');

        $this->assertSame(0, Musician::query()->where('id', $person->id)->where('active', true)->count());

        $this->actingAs($user)
            ->post(route('people.restore', $person))
            ->assertRedirect(route('people.index'));

        $person->refresh();
        $this->assertTrue($person->active);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('Archive Candidate');
    }

    public function test_archiving_person_does_not_delete_user_account(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)->post(route('people.store'), [
            'first_name' => 'Logged',
            'last_name' => 'In',
            'email' => 'logged.in@example.test',
            'create_login_account' => '1',
            'band_roles' => [BandRole::Musician->value],
        ]);

        $person = Musician::query()->where('email', 'logged.in@example.test')->firstOrFail();
        $loginUserId = $person->user_id;
        $this->assertNotNull($loginUserId);

        $this->actingAs($user)->post(route('people.archive', $person));

        $this->assertDatabaseHas('users', ['id' => $loginUserId]);
        $this->assertDatabaseHas('musicians', ['id' => $person->id, 'active' => false]);
        $this->assertSame($loginUserId, $person->fresh()->user_id);
    }
}
