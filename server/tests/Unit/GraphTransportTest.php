<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class GraphTransportTest extends TestCase
{
    public function test_sends_message_through_graph_send_mail_endpoint(): void
    {
        $this->configureGraphMail('bookings@example.com');

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-token-123',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/*' => Http::response('', 202),
        ]);

        Mail::raw('Graph transport body', function ($message): void {
            $message->to('recipient@example.com')
                ->subject('Graph transport subject');
        });

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'graph.microsoft.com/v1.0/users/bookings%40example.com/sendMail')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer graph-token-123')
                && ($request['message']['subject'] ?? null) === 'Graph transport subject'
                && ($request['message']['body']['content'] ?? null) === 'Graph transport body';
        });
    }

    public function test_requires_send_as_mailbox(): void
    {
        $this->configureGraphMail('');

        Http::fake();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Microsoft Graph send-as mailbox is not configured.');

        Mail::raw('Body', function ($message): void {
            $message->to('recipient@example.com')->subject('Subject');
        });
    }

    private function configureGraphMail(string $sendAs): void
    {
        config([
            'mail.default' => 'graph',
            'mail.mailers.graph.send_as' => $sendAs,
            'services.microsoft.tenant_id' => 'tenant-id',
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
            'services.microsoft.send_as' => $sendAs,
        ]);
    }
}
