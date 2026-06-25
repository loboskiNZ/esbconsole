<?php

namespace Tests\Feature;

use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\CreatesInviteLinks;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioBandInvitesTest extends TestCase
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

    public function test_studio_dashboard_shows_empty_state_with_create_invite_for_director(): void
    {
        $user = $this->createDirectorUser();

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Band Invites', false)
            ->assertSee('No active band invites.', false)
            ->assertSee('Create invite', false)
            ->assertSee('esb-studio__band-invites', false)
            ->assertSee(route('studio.invites.store'), false);
    }

    public function test_musician_does_not_see_band_invites_card(): void
    {
        $user = User::factory()->create();
        $this->assignMusicianRole($user);

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertDontSee('Band Invites', false)
            ->assertDontSee('esb-studio__band-invites', false);
    }

    public function test_active_invite_with_token_ciphertext_renders_qr_share_card(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        try {
            $user = $this->createDirectorUser();
            $token = $this->createInviteLinkToken([
                'name' => 'Guitar Audition',
                'expires_at' => Carbon::parse('2026-07-02 12:00:00'),
            ]);

            $inviteUrl = 'https://band.example.test/invite/'.$token;

            $this->actingAs($user)->get('/studio')
                ->assertOk()
                ->assertSee('Guitar Audition', false)
                ->assertSee('Expires 02 Jul 2026', false)
                ->assertSee($inviteUrl, false)
                ->assertSee('data-invite-qr', false)
                ->assertSee('Copy link', false)
                ->assertSee('Open', false)
                ->assertSee('Download QR', false)
                ->assertSee('esb-studio__band-invite-share', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expired_invite_does_not_render_qr_share_card(): void
    {
        $user = $this->createDirectorUser();

        $this->createInviteLinkToken([
            'name' => 'Past Audition',
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertDontSee('Past Audition', false)
            ->assertDontSee('data-invite-qr', false)
            ->assertSee('No active band invites.', false);
    }

    public function test_invite_without_token_ciphertext_does_not_render_as_shareable(): void
    {
        $this->ensureInviteLinksTable();

        $user = $this->createDirectorUser();
        $rawToken = InviteLink::generateRawToken();

        InviteLink::create([
            'name' => 'Legacy Invite',
            'token_hash' => InviteLink::hashToken($rawToken),
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertDontSee('Legacy Invite', false)
            ->assertDontSee($rawToken, false)
            ->assertDontSee('data-invite-qr', false)
            ->assertSee('older invite', false)
            ->assertSee('No active band invites.', false);
    }

    public function test_generated_invite_url_uses_configured_application_url(): void
    {
        $user = $this->createDirectorUser();
        $token = $this->createInviteLinkToken(['name' => 'Section Invite']);

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('https://band.example.test/invite/'.$token, false);
    }

    public function test_director_can_create_invite_from_studio(): void
    {
        $user = $this->createDirectorUser();

        $this->actingAs($user)->get('/studio');

        $this->actingAs($user)
            ->post(route('studio.invites.store'), [
                '_token' => session()->token(),
                'name' => 'Horn Section',
                'days' => 14,
            ])->assertRedirect(route('studio'));

        $invite = InviteLink::query()->where('name', 'Horn Section')->first();

        $this->assertNotNull($invite);
        $this->assertNotNull($invite->token_ciphertext);
        $this->assertTrue($invite->isValid());

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Band invite created.', false)
            ->assertSee('Horn Section', false)
            ->assertSee((string) $invite->inviteUrl(), false);
    }

    public function test_musician_cannot_create_invite(): void
    {
        $user = User::factory()->create();
        $this->assignMusicianRole($user);

        $this->actingAs($user)->get('/studio');

        $this->actingAs($user)
            ->post(route('studio.invites.store'), [
                '_token' => session()->token(),
                'name' => 'Blocked Invite',
            ])->assertForbidden();

        $this->assertSame(0, InviteLink::query()->count());
    }
}
