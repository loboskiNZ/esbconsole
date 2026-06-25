<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\StudioRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioRolesTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal.band_id' => 1]);
        $this->ensurePortalBand();
    }

    public function test_roles_are_created_idempotently(): void
    {
        $this->assertSame(4, Role::query()->count());

        $provisioner = app(StudioRoleProvisioner::class);
        $secondRun = $provisioner->provisionSystemRoles();

        $this->assertSame(0, $secondRun);
        $this->assertSame(4, Role::query()->count());
        $this->assertDatabaseHas('roles', ['role_key' => Role::KEY_DIRECTOR, 'name' => 'Director / Superuser']);
        $this->assertDatabaseHas('roles', ['role_key' => Role::KEY_MUSICIAN]);
        $this->assertDatabaseHas('roles', ['role_key' => Role::KEY_SOUND_TECH]);
        $this->assertDatabaseHas('roles', ['role_key' => Role::KEY_ASSISTANT]);
    }

    public function test_ed_loboski_is_assigned_director_role(): void
    {
        $user = User::factory()->create([
            'username' => StudioRoleProvisioner::DIRECTOR_USERNAME,
            'email' => StudioRoleProvisioner::DIRECTOR_EMAIL,
        ]);

        $result = app(StudioRoleProvisioner::class)->provision();

        $this->assertTrue($result['director_assigned']);
        $this->assertSame($user->id, $result['director_user_id']);
        $this->assertTrue($user->fresh()->isDirector());
    }

    public function test_director_assignment_does_not_duplicate_on_repeated_run(): void
    {
        $user = User::factory()->create([
            'username' => StudioRoleProvisioner::DIRECTOR_USERNAME,
            'email' => StudioRoleProvisioner::DIRECTOR_EMAIL,
        ]);

        app(StudioRoleProvisioner::class)->provision();
        app(StudioRoleProvisioner::class)->provision();

        $this->assertSame(1, UserRole::query()->where('user_id', $user->id)->count());
    }

    public function test_provision_preserves_existing_user_data(): void
    {
        $user = User::factory()->create([
            'username' => StudioRoleProvisioner::DIRECTOR_USERNAME,
            'email' => StudioRoleProvisioner::DIRECTOR_EMAIL,
        ]);

        $before = DB::table('users')->where('id', $user->id)->first();
        $usersCountBefore = DB::table('users')->count();

        $result = app(StudioRoleProvisioner::class)->provision();

        $after = DB::table('users')->where('id', $user->id)->first();

        $this->assertSame($usersCountBefore, $result['users_count_before']);
        $this->assertSame($usersCountBefore, $result['users_count_after']);
        $this->assertSame($before->username, $after->username);
        $this->assertSame($before->email, $after->email);
        $this->assertSame($before->password, $after->password);
        $this->assertSame($before->person_id, $after->person_id);
    }

    public function test_director_helper_returns_true_for_ed(): void
    {
        $user = $this->createDirectorUser([
            'username' => StudioRoleProvisioner::DIRECTOR_USERNAME,
            'email' => StudioRoleProvisioner::DIRECTOR_EMAIL,
        ]);

        $this->assertTrue($user->isDirector());
        $this->assertTrue($user->hasRole(Role::KEY_DIRECTOR));
    }

    public function test_non_director_user_does_not_pass_director_check(): void
    {
        $user = User::factory()->create();
        $this->assignMusicianRole($user);

        $this->assertFalse($user->isDirector());
        $this->assertTrue($user->hasRole(Role::KEY_MUSICIAN));
    }

    public function test_migrations_create_role_tables_and_seed_roles(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('user_roles'));
        $this->assertSame(4, Role::query()->count());
    }

    public function test_provision_assigns_director_after_user_exists(): void
    {
        $user = User::factory()->create([
            'username' => StudioRoleProvisioner::DIRECTOR_USERNAME,
            'email' => StudioRoleProvisioner::DIRECTOR_EMAIL,
        ]);

        app(StudioRoleProvisioner::class)->provision();

        $this->assertTrue($user->fresh()->isDirector());
    }

    public function test_provision_command_is_idempotent(): void
    {
        User::factory()->create([
            'username' => StudioRoleProvisioner::DIRECTOR_USERNAME,
            'email' => StudioRoleProvisioner::DIRECTOR_EMAIL,
        ]);

        Artisan::call('esb:provision-studio-roles');
        Artisan::call('esb:provision-studio-roles');

        $this->assertSame(4, Role::query()->count());
        $this->assertSame(1, UserRole::query()->count());
        $this->assertSame(0, Artisan::call('esb:provision-studio-roles'));
    }

    public function test_studio_still_loads_for_director_and_musician(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($director)->get('/studio')->assertOk()->assertSee('Welcome to Studio', false);
        $this->actingAs($musician)->get('/studio')->assertOk()->assertSee('Welcome to Studio', false);
    }
}
