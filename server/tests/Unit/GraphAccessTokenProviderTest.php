<?php

namespace Tests\Unit;

use App\Services\MicrosoftGraph\GraphAccessTokenProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GraphAccessTokenProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_acquires_and_caches_access_token(): void
    {
        config([
            'services.microsoft.tenant_id' => 'tenant-id',
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-token-123',
                'expires_in' => 3600,
            ]),
        ]);

        $provider = app(GraphAccessTokenProvider::class);

        $this->assertSame('graph-token-123', $provider->getAccessToken());
        $this->assertSame('graph-token-123', $provider->getAccessToken());

        Http::assertSentCount(1);
    }

    public function test_throws_when_credentials_are_missing(): void
    {
        config([
            'services.microsoft.tenant_id' => '',
            'services.microsoft.client_id' => '',
            'services.microsoft.client_secret' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Microsoft Graph mail credentials are not configured.');

        app(GraphAccessTokenProvider::class)->getAccessToken();
    }
}
