<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\InviteLink;
use App\Models\Role;
use App\Models\User;
use App\Services\OnboardingRegistrationService;
use App\Services\StudioRoleProvisioner;
use App\Services\StudioUserManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\CreatesInviteLinks;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioUserManagementTest extends TestCase
{
    use AssignsStudioRoles;
    use CreatesInviteLinks;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal.band_id' => 1,
            'app.url' => 'https://band.example.test',
        ]);

        $this->ensurePortalBand();
        $this->ensureInviteLinksTable();
    }

    public function test_director_can_view_users_page(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio/users')
            ->assertOk()
            ->assertSee('Users', false)
            ->assertSee('Manage Studio accounts, access, and roles.', false);
    }

    public function test_musician_cannot_view_users_page(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)->get('/studio/users')->assertForbidden();
    }

    public function test_users_list_shows_roles_and_active_status(): void
    {
        $director = $this->createDirectorUser([
            'username' => 'showcase',
            'name' => 'Showcase Director',
            'email' => 'showcase@example.com',
        ]);

        $musician = User::factory()->create([
            'username' => 'activeplayer',
            'name' => 'Active Player',
            'email' => 'active@example.com',
            'is_active' => true,
        ]);
        $this->assignMusicianRole($musician);

        $inactive = User::factory()->create([
            'username' => 'inactiveplayer',
            'name' => 'Inactive Player',
            'is_active' => false,
        ]);
        $this->assignMusicianRole($inactive);

        $this->actingAs($director)->get('/studio/users')
            ->assertOk()
            ->assertSee('showcase', false)
            ->assertSee('Active', false)
            ->assertSee('Inactive', false)
            ->assertSee('Director / Superuser', false)
            ->assertSee('Musician', false);
    }

    public function test_director_can_deactivate_another_user(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create(['username' => 'deactivateme']);
        $this->assignMusicianRole($musician);

        $this->beginUsersManagementSession($director)
            ->patch(route('studio.users.deactivate', $musician), [
                '_token' => session()->token(),
            ])
            ->assertRedirect(route('studio.users.index'));

        $this->assertFalse($musician->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $musician = User::factory()->create([
            'username' => 'lockedout',
            'is_active' => false,
        ]);
        $this->assignMusicianRole($musician);

        $this->get('/');

        $this->post('/login', [
            'username' => 'lockedout',
            'password' => 'Password1!',
            '_token' => session()->token(),
        ])->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_director_can_reactivate_user(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create([
            'username' => 'reactivateme',
            'is_active' => false,
        ]);
        $this->assignMusicianRole($musician);

        $this->beginUsersManagementSession($director)
            ->patch(route('studio.users.activate', $musician), [
                '_token' => session()->token(),
            ])
            ->assertRedirect(route('studio.users.index'));

        $this->assertTrue($musician->fresh()->is_active);
    }

    public function test_director_can_assign_sound_tech_role(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create(['username' => 'techcandidate']);
        $this->assignMusicianRole($musician);

        $this->beginUsersManagementSession($director)
            ->put(route('studio.users.roles.update', $musician), [
                '_token' => session()->token(),
                'roles' => [Role::KEY_MUSICIAN, Role::KEY_SOUND_TECH],
            ])
            ->assertRedirect(route('studio.users.index'));

        $musician->unsetRelation('roles');

        $this->assertTrue($musician->fresh()->hasRole(Role::KEY_SOUND_TECH));
        $this->assertTrue($musician->fresh()->hasRole(Role::KEY_MUSICIAN));
    }

    public function test_director_can_remove_assistant_role(): void
    {
        $director = $this->createDirectorUser();
        $user = User::factory()->create(['username' => 'assistantdrop']);
        $this->assignMusicianRole($user);
        $this->assignRole($user, Role::KEY_ASSISTANT);

        $this->beginUsersManagementSession($director)
            ->put(route('studio.users.roles.update', $user), [
                '_token' => session()->token(),
                'roles' => [Role::KEY_MUSICIAN],
            ])
            ->assertRedirect(route('studio.users.index'));

        $user->unsetRelation('roles');

        $this->assertTrue($user->fresh()->hasRole(Role::KEY_MUSICIAN));
        $this->assertFalse($user->fresh()->hasRole(Role::KEY_ASSISTANT));
    }

    public function test_default_onboarding_user_gets_musician_role(): void
    {
        $user = User::factory()->create(['username' => 'newmusician']);

        app(StudioUserManagementService::class)->assignDefaultMusicianRole($user);

        $user->unsetRelation('roles');

        $this->assertTrue($user->fresh()->hasRole(Role::KEY_MUSICIAN));
    }

    public function test_onboarding_registration_service_assigns_default_musician_role(): void
    {
        $this->ensureOnboardingInstrumentCatalog();
        $this->ensureInviteLinkAcceptancesTable();

        $token = $this->createInviteLinkToken();
        $inviteLink = InviteLink::query()
            ->where('token_hash', InviteLink::hashToken($token))
            ->firstOrFail();

        $user = app(OnboardingRegistrationService::class)->register($inviteLink, [
            'username' => 'onboardedplayer',
            'password' => 'Password1!',
            'password_confirm' => 'Password1!',
            'honeypot' => '',
            'first_name' => 'Onboarded',
            'middle_name' => '',
            'surname' => 'Player',
            'stage_name' => 'Onboarded Player',
            'primary_instrument' => 'scaffold-vocals',
            'additional_instruments' => [],
            'email' => 'onboarded@example.com',
            'country' => 'New Zealand',
            'country_iso3' => 'NZL',
            'city' => 'Dunedin',
            'telephone' => '+64 21 000 0000',
        ]);

        $user->unsetRelation('roles');

        $this->assertTrue($user->fresh()->hasRole(Role::KEY_MUSICIAN));
    }

    public function test_cannot_remove_last_director_role(): void
    {
        $director = $this->createDirectorUser(['username' => 'solodirector']);

        $this->beginUsersManagementSession($director)
            ->put(route('studio.users.roles.update', $director), [
                '_token' => session()->token(),
                'roles' => [Role::KEY_MUSICIAN],
            ])
            ->assertRedirect('/studio/users')
            ->assertSessionHasErrors('roles');

        $director->unsetRelation('roles');
        $this->assertTrue($director->fresh()->isDirector());
    }

    public function test_cannot_deactivate_last_active_director(): void
    {
        $director = $this->createDirectorUser(['username' => 'solodirector2']);

        $this->beginUsersManagementSession($director)
            ->patch(route('studio.users.deactivate', $director), [
                '_token' => session()->token(),
            ])
            ->assertRedirect('/studio/users')
            ->assertSessionHasErrors('user');

        $this->assertTrue($director->fresh()->is_active);
    }

    public function test_user_management_never_deletes_user_or_person_rows(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create(['username' => 'preserveme']);
        $this->assignMusicianRole($musician);
        $this->assignRole($musician, Role::KEY_ASSISTANT);

        $usersBefore = DB::table('users')->count();
        $peopleBefore = DB::table('people')->count();

        $this->beginUsersManagementSession($director)
            ->patch(route('studio.users.deactivate', $musician), [
                '_token' => session()->token(),
            ])
            ->assertRedirect(route('studio.users.index'));

        $this->beginUsersManagementSession($director)
            ->put(route('studio.users.roles.update', $musician), [
                '_token' => session()->token(),
                'roles' => [Role::KEY_MUSICIAN, Role::KEY_SOUND_TECH],
            ])
            ->assertRedirect(route('studio.users.index'));

        $this->beginUsersManagementSession($director)
            ->patch(route('studio.users.activate', $musician), [
                '_token' => session()->token(),
            ])
            ->assertRedirect(route('studio.users.index'));

        $this->assertSame($usersBefore, DB::table('users')->count());
        $this->assertSame($peopleBefore, DB::table('people')->count());
        $this->assertDatabaseHas('users', ['id' => $musician->id, 'username' => 'preserveme']);
        $this->assertDatabaseHas('people', ['id' => $musician->person_id]);
    }

    public function test_director_sees_manage_users_link_on_studio_dashboard(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Manage Users', false)
            ->assertSee(route('studio.users.index'), false);
    }

    public function test_musician_does_not_see_manage_users_link(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)->get('/studio')
            ->assertOk()
            ->assertDontSee('Manage Users', false)
            ->assertDontSee(route('studio.users.index'), false);
    }

    private function assignRole(User $user, string $roleKey): void
    {
        $this->provisionStudioRoles();

        app(StudioRoleProvisioner::class)->assignRoleToUser(
            roleKey: $roleKey,
            userId: $user->id,
            bandId: (int) config('portal.band_id', 1),
        );

        $user->unsetRelation('roles');
    }

    private function beginUsersManagementSession(User $director): self
    {
        $this->actingAs($director)->get('/studio/users')->assertOk();

        return $this;
    }

    private function ensureOnboardingInstrumentCatalog(): void
    {
        if (InstrumentReference::query()->where('slug', 'scaffold-vocals')->exists()) {
            return;
        }

        InstrumentReference::query()->create([
            'public_id' => (string) Str::uuid(),
            'slug' => 'scaffold-vocals',
            'name' => 'Vocals',
            'family' => 'Voice',
            'is_active' => true,
        ]);
    }

    private function ensureInviteLinkAcceptancesTable(): void
    {
        if (Schema::hasTable('invite_link_acceptances')) {
            return;
        }

        Schema::create('invite_link_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invite_link_id')->constrained('invite_links')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }
}
