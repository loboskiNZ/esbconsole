<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\StudioRoleProvisioner;
use App\Services\StudioRolesSchemaReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioRolesLegacyCompatibilityTest extends TestCase
{
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal.band_id' => 1]);
        $this->ensurePortalBand();
        $this->seedLegacyRolesTable();
    }

    public function test_reconciler_adds_public_id_and_role_key_to_legacy_roles_table(): void
    {
        app(StudioRolesSchemaReconciler::class)->reconcile();

        $this->assertTrue(Schema::hasColumn('roles', 'public_id'));
        $this->assertTrue(Schema::hasColumn('roles', 'role_key'));
        $this->assertTrue(Schema::hasColumn('roles', 'description'));
        $this->assertTrue(Schema::hasColumn('roles', 'is_system'));
        $this->assertTrue(Schema::hasTable('user_roles'));
    }

    public function test_existing_legacy_role_rows_are_preserved(): void
    {
        app(StudioRolesSchemaReconciler::class)->reconcile();

        $legacy = DB::table('roles')->where('id', 1)->first();

        $this->assertNotNull($legacy);
        $this->assertSame('Legacy Stage Manager', $legacy->name);
        $this->assertNull($legacy->role_key);
        $this->assertNotNull($legacy->public_id);
    }

    public function test_provisioner_creates_studio_roles_after_legacy_reconcile(): void
    {
        app(StudioRolesSchemaReconciler::class)->reconcile();

        $rolesCountBefore = DB::table('roles')->count();
        $created = app(StudioRoleProvisioner::class)->provisionSystemRoles();

        $this->assertSame(4, $created);
        $this->assertSame($rolesCountBefore + 4, DB::table('roles')->count());
        $this->assertDatabaseHas('roles', ['role_key' => Role::KEY_DIRECTOR, 'name' => 'Director / Superuser']);
        $this->assertSame('Legacy Stage Manager', DB::table('roles')->where('id', 1)->value('name'));
    }

    public function test_repeated_reconcile_and_provisioning_is_idempotent(): void
    {
        app(StudioRolesSchemaReconciler::class)->reconcile();
        app(StudioRoleProvisioner::class)->provision();
        app(StudioRolesSchemaReconciler::class)->reconcile();
        $secondProvision = app(StudioRoleProvisioner::class)->provision();

        $this->assertSame(0, $secondProvision['roles_created']);
        $this->assertSame(5, Role::query()->count());
    }

    public function test_ed_loboski_director_assignment_works_after_legacy_reconcile(): void
    {
        $user = User::factory()->create([
            'username' => StudioRoleProvisioner::DIRECTOR_USERNAME,
            'email' => StudioRoleProvisioner::DIRECTOR_EMAIL,
        ]);

        app(StudioRolesSchemaReconciler::class)->reconcile();
        $result = app(StudioRoleProvisioner::class)->provision();

        $this->assertTrue($result['director_assigned']);
        $this->assertTrue($user->fresh()->isDirector());
        $this->assertSame(1, UserRole::query()->where('user_id', $user->id)->count());
    }

    public function test_compatibility_migration_can_be_re_run_safely(): void
    {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_01_001315_reconcile_studio_roles_legacy_schema.php',
            '--force' => true,
        ]);

        $legacyName = DB::table('roles')->where('id', 1)->value('name');

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_01_001315_reconcile_studio_roles_legacy_schema.php',
            '--force' => true,
        ]);

        $this->assertSame('Legacy Stage Manager', $legacyName);
        $this->assertSame('Legacy Stage Manager', DB::table('roles')->where('id', 1)->value('name'));
    }

    private function seedLegacyRolesTable(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');

        Schema::create('roles', function ($table): void {
            $table->id();
            $table->string('name');
        });

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'Legacy Stage Manager',
        ]);
    }
}
