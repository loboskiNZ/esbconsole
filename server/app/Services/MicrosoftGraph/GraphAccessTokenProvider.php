<?php

namespace App\Services\MicrosoftGraph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GraphAccessTokenProvider
{
    private const CACHE_KEY = 'microsoft_graph_app_access_token';

    /**
     * @return array{tenant_id: string, client_id: string, client_secret: string}
     */
    public function credentials(): array
    {
        $tenantId = (string) config('services.microsoft.tenant_id', '');
        $clientId = (string) config('services.microsoft.client_id', '');
        $clientSecret = (string) config('services.microsoft.client_secret', '');

        if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Microsoft Graph mail credentials are not configured.');
        }

        return [
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }

    public function getAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();

        $response = Http::asForm()
            ->timeout(15)
            ->post(
                'https://login.microsoftonline.com/'.$credentials['tenant_id'].'/oauth2/v2.0/token',
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'scope' => 'https://graph.microsoft.com/.default',
                ],
            );

        if (! $response->successful()) {
            throw new RuntimeException('Unable to acquire Microsoft Graph access token.');
        }

        $token = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if ($token === '') {
            throw new RuntimeException('Microsoft Graph access token response was empty.');
        }

        Cache::put(self::CACHE_KEY, $token, max(60, $expiresIn - 120));

        return $token;
    }
}
