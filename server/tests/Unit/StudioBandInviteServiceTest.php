<?php

namespace Tests\Unit;

use App\Models\InviteLink;
use App\Services\StudioBandInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesInviteLinks;
use Tests\TestCase;

class StudioBandInviteServiceTest extends TestCase
{
    use CreatesInviteLinks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://band.example.test']);
        $this->ensureInviteLinksTable();
    }

    public function test_shareable_invite_url_uses_configured_application_url(): void
    {
        $token = $this->createInviteLinkToken(['name' => 'Section Invite']);

        $invite = app(StudioBandInviteService::class)
            ->shareableInvitesForDashboard()
            ->first();

        $this->assertNotNull($invite);
        $this->assertSame('https://band.example.test/invite/'.$token, $invite['invite_url']);
    }

    public function test_expired_invite_is_not_shareable(): void
    {
        $this->createInviteLinkToken([
            'name' => 'Expired Invite',
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertTrue(app(StudioBandInviteService::class)->shareableInvitesForDashboard()->isEmpty());
    }

    public function test_legacy_invite_without_ciphertext_is_not_shareable(): void
    {
        InviteLink::create([
            'name' => 'Legacy Invite',
            'token_hash' => InviteLink::hashToken(InviteLink::generateRawToken()),
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $service = app(StudioBandInviteService::class);

        $this->assertTrue($service->shareableInvitesForDashboard()->isEmpty());
        $this->assertSame(1, $service->legacyUnusableCount());
    }

    public function test_create_invite_persists_encrypted_token(): void
    {
        $invite = app(StudioBandInviteService::class)->createInvite('New Audition', 21);

        $this->assertSame('New Audition', $invite->name);
        $this->assertNotNull($invite->token_ciphertext);
        $this->assertTrue($invite->isValid());
        $this->assertNotNull($invite->inviteUrl());
    }
}
