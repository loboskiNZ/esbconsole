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

        $this->ensureInviteLinksTable();
    }

    public function test_invite_url_uses_configured_application_url(): void
    {
        config(['app.url' => 'https://band.example.test']);

        $token = $this->createInviteLinkToken(['name' => 'Section Invite']);

        $invite = app(StudioBandInviteService::class)
            ->invitesForDashboard()
            ->first();

        $this->assertNotNull($invite);
        $this->assertSame('https://band.example.test/invite/'.$token, $invite['invite_url']);
    }

    public function test_expiry_label_uses_expired_for_past_dates(): void
    {
        $this->createInviteLinkToken([
            'name' => 'Expired Invite',
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $invite = app(StudioBandInviteService::class)
            ->invitesForDashboard()
            ->first();

        $this->assertNotNull($invite);
        $this->assertSame('(Expired)', $invite['expiry_label']);
        $this->assertFalse($invite['is_active']);
    }
}
