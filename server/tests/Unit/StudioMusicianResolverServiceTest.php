<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StudioMusicianResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioMusicianResolverServiceTest extends TestCase
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

    public function test_resolves_active_musician_by_user_id(): void
    {
        $user = User::factory()->create(['band_id' => 1]);
        $musicianId = $this->seedMusician([
            'user_id' => $user->id,
            'display_name' => 'Linked Player',
        ]);

        $resolved = app(StudioMusicianResolverService::class)->musicianForUser($user);

        $this->assertNotNull($resolved);
        $this->assertSame($musicianId, $resolved->id);
    }

    public function test_resolves_musician_by_user_email_when_person_email_missing(): void
    {
        $user = User::factory()->create([
            'band_id' => 1,
            'email' => 'linked@example.com',
            'name' => 'Stage Player',
        ]);
        $user->person->update(['email' => null, 'artistic_name' => 'Stage Player']);

        $musicianId = $this->seedMusician([
            'user_id' => null,
            'email' => 'linked@example.com',
            'display_name' => 'Stage Player',
        ]);

        $resolved = app(StudioMusicianResolverService::class)->musicianForUser($user->fresh(['person']));

        $this->assertNotNull($resolved);
        $this->assertSame($musicianId, $resolved->id);
    }

    public function test_resolves_musician_by_case_insensitive_email(): void
    {
        $user = User::factory()->create([
            'band_id' => 1,
            'email' => 'Player@Example.com',
        ]);
        $user->person->update(['email' => null]);

        $musicianId = $this->seedMusician([
            'user_id' => null,
            'email' => 'player@example.com',
            'display_name' => 'Case Player',
        ]);

        $resolved = app(StudioMusicianResolverService::class)->musicianForUser($user->fresh(['person']));

        $this->assertSame($musicianId, $resolved?->id);
    }

    public function test_resolves_musician_by_stage_name_when_user_id_unset(): void
    {
        $user = User::factory()->create([
            'band_id' => 1,
            'email' => 'different@example.com',
            'name' => 'Shadow Player',
        ]);
        $user->person->update([
            'email' => 'another@example.com',
            'artistic_name' => 'Shadow Player',
            'legal_first_name' => 'Legal',
            'legal_last_name' => 'Name',
        ]);

        $musicianId = $this->seedMusician([
            'user_id' => null,
            'email' => 'roster@example.com',
            'display_name' => 'Shadow Player',
            'first_name' => 'Other',
            'last_name' => 'Person',
        ]);

        $resolved = app(StudioMusicianResolverService::class)->musicianForUser($user->fresh(['person']));

        $this->assertSame($musicianId, $resolved?->id);
    }

    public function test_resolves_musician_by_user_id_even_when_marked_inactive(): void
    {
        $user = User::factory()->create(['band_id' => 1]);

        $musicianId = $this->seedMusician([
            'user_id' => $user->id,
            'display_name' => 'Linked Player',
            'active' => false,
        ]);

        $resolved = app(StudioMusicianResolverService::class)->musicianForUser($user);

        $this->assertSame($musicianId, $resolved?->id);
    }

    public function test_does_not_resolve_inactive_musician_without_user_id_link(): void
    {
        $user = User::factory()->create([
            'band_id' => 1,
            'email' => 'inactive@example.com',
            'name' => 'Inactive Player',
        ]);
        $user->person->update(['email' => null, 'artistic_name' => 'Inactive Player']);

        $this->seedMusician([
            'user_id' => null,
            'email' => 'inactive@example.com',
            'display_name' => 'Inactive Player',
            'active' => false,
        ]);

        $this->assertNull(app(StudioMusicianResolverService::class)->musicianForUser($user->fresh(['person'])));
    }

    public function test_does_not_resolve_musician_from_another_band(): void
    {
        DB::table('bands')->insert([
            'id' => 2,
            'public_id' => (string) Str::uuid(),
            'name' => 'Other Band',
            'primary_director_musician_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create(['band_id' => 1, 'email' => 'bandone@example.com']);
        $user->person->update(['email' => null]);

        $this->seedMusician([
            'band_id' => 2,
            'user_id' => null,
            'email' => 'bandone@example.com',
            'display_name' => 'Other Band Player',
        ]);

        $this->assertNull(app(StudioMusicianResolverService::class)->musicianForUser($user->fresh(['person'])));
    }

    public function test_resolves_musician_by_username_when_display_name_differs_by_case(): void
    {
        $user = User::factory()->create([
            'band_id' => 1,
            'username' => 'demo',
            'email' => 'demo@example.com',
            'name' => 'Demo',
        ]);
        $user->person->update([
            'email' => 'different@example.com',
            'artistic_name' => 'Demo',
        ]);

        $musicianId = $this->seedMusician([
            'user_id' => null,
            'email' => null,
            'display_name' => 'demo',
            'first_name' => 'Demo',
            'last_name' => 'Account',
        ]);

        $resolved = app(StudioMusicianResolverService::class)->musicianForUser($user->fresh(['person']));

        $this->assertSame($musicianId, $resolved?->id);
    }

    public function test_resolves_musician_on_portal_band_when_user_band_id_is_wrong(): void
    {
        DB::table('bands')->insert([
            'id' => 2,
            'public_id' => (string) Str::uuid(),
            'name' => 'Other Band',
            'primary_director_musician_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'band_id' => 2,
            'username' => 'demo',
            'email' => 'ed@loboski.co.uk',
            'name' => 'Demo',
        ]);
        $user->person->update(['email' => 'ed@loboski.co.uk', 'artistic_name' => 'Demo']);

        $musicianId = $this->seedMusician([
            'user_id' => null,
            'email' => 'ed@loboski.co.uk',
            'display_name' => 'Demo',
        ]);

        $resolved = app(StudioMusicianResolverService::class)->musicianForUser($user->fresh(['person']));

        $this->assertSame($musicianId, $resolved?->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedMusician(array $overrides = []): int
    {
        return (int) DB::table('musicians')->insertGetId(array_merge([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'user_id' => null,
            'first_name' => 'Test',
            'last_name' => 'Musician',
            'display_name' => 'Test Musician',
            'email' => null,
            'notes' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
