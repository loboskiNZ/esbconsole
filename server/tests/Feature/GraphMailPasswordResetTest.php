<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class GraphMailPasswordResetTest extends TestCase
{
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePortalBand();

        config([
            'mail.default' => 'graph',
            'mail.mailers.graph.send_as' => 'bookings@example.com',
            'mail.from.address' => 'bookings@example.com',
            'mail.from.name' => 'ESB Cloud Studio',
            'services.microsoft.tenant_id' => 'tenant-id',
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
            'services.microsoft.send_as' => 'bookings@example.com',
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-token-123',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/*' => Http::response('', 202),
        ]);
    }

    public function test_password_reset_request_dispatches_graph_mail(): void
    {
        User::factory()->create([
            'email' => 'reset@example.com',
            'username' => 'resetuser',
            'is_active' => true,
        ]);

        $this->get(route('password.request'));

        $this->post(route('password.email'), [
            '_token' => session()->token(),
            'email' => 'reset@example.com',
        ])->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'graph.microsoft.com/v1.0/users/bookings%40example.com/sendMail')) {
                return false;
            }

            $subject = (string) ($request['message']['subject'] ?? '');

            return str_contains($subject, 'Reset your Ed and the Shadow Boys portal password')
                && ($request['message']['toRecipients'][0]['emailAddress']['address'] ?? null) === 'reset@example.com';
        });
    }
}
