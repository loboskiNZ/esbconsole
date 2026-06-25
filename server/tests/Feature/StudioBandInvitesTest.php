<?php

namespace Tests\Feature;

use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesInviteLinks;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioBandInvitesTest extends TestCase
{
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
    }

    public function test_studio_dashboard_shows_band_invites_card_below_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Band Invites', false)
            ->assertSee('No active band invites.', false)
            ->assertSee('esb-studio__band-invites', false);
    }

    public function test_studio_dashboard_lists_invites_newest_first_with_expiry_and_actions(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        try {
            $user = User::factory()->create();

            $olderToken = $this->createInviteLinkToken([
                'name' => 'Guitar Audition',
                'expires_at' => Carbon::parse('2026-07-02 12:00:00'),
            ]);

            $newerToken = $this->createInviteLinkToken([
                'name' => 'Horn Section',
                'expires_at' => Carbon::parse('2026-07-10 12:00:00'),
            ]);

            InviteLink::query()->where('name', 'Guitar Audition')->update([
                'created_at' => Carbon::parse('2026-06-10 12:00:00'),
                'updated_at' => Carbon::parse('2026-06-10 12:00:00'),
            ]);

            InviteLink::query()->where('name', 'Horn Section')->update([
                'created_at' => Carbon::parse('2026-06-15 12:00:00'),
                'updated_at' => Carbon::parse('2026-06-15 12:00:00'),
            ]);

            $response = $this->actingAs($user)->get('/studio');

            $response->assertOk();
            $response->assertSeeInOrder([
                'Horn Section',
                $newerToken,
                '(expires 10 Jul 2026)',
                'Guitar Audition',
                $olderToken,
                '(expires 02 Jul 2026)',
            ], false);
            $response->assertSee('https://band.example.test/invite/'.$newerToken, false);
            $response->assertSee('Copy', false);
            $response->assertSee('Open', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expired_invites_are_greyed_out_with_expired_label(): void
    {
        $user = User::factory()->create();

        $this->createInviteLinkToken([
            'name' => 'Past Audition',
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Past Audition', false)
            ->assertSee('(Expired)', false)
            ->assertSee('esb-studio__band-invite--inactive', false);
    }

    public function test_legacy_invites_without_ciphertext_show_unavailable_slug(): void
    {
        $this->ensureInviteLinksTable();

        $user = User::factory()->create();
        $rawToken = InviteLink::generateRawToken();

        InviteLink::create([
            'name' => 'Legacy Invite',
            'token_hash' => InviteLink::hashToken($rawToken),
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Legacy Invite', false)
            ->assertSee('Slug unavailable', false)
            ->assertDontSee($rawToken, false)
            ->assertDontSee('Copy', false);
    }
}
