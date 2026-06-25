<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\StudioRoleProvisioner;
use App\Support\StudioUserRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioUserRolesTest extends TestCase
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

    public function test_has_role_matches_band_scoped_assignment(): void
    {
        $user = User::factory()->create();
        $this->assignDirectorRole($user, bandId: 1);

        $this->assertTrue(StudioUserRoles::hasRole($user, Role::CODE_DIRECTOR, 1));
        $this->assertFalse(StudioUserRoles::hasRole($user, Role::CODE_DIRECTOR, 99));
    }

    public function test_find_director_user_matches_person_email(): void
    {
        $user = User::factory()->create([
            'username' => 'other-user',
            'email' => null,
        ]);

        $user->person->update(['email' => StudioRoleProvisioner::DIRECTOR_EMAIL]);

        $found = app(StudioRoleProvisioner::class)->findDirectorUser();

        $this->assertNotNull($found);
        $this->assertSame($user->id, (int) $found->id);
    }
}
