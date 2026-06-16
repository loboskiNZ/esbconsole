<?php

namespace App\Services\X32;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes routing.source_connectivity from live /-stat/* OSC reads.
 */
class X32SourceConnectivityService
{
    public function __construct(
        private readonly X32SourceConnectivityCapture $capture,
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function enrichSummaryWithLiveConnectivity(array $summary, ?IntegrationDevice $device): array
    {
        if ($device === null || ! $this->runtimeModeResolver->isLive(
            $this->runtimeModeResolver->resolve($device->configuration),
        )) {
            return $summary;
        }

        $profile = $device->integrationConnectionProfiles()
            ->where('enabled', true)
            ->whereIn('protocol', [
                IntegrationConnectionProfile::PROTOCOL_OSC,
                IntegrationConnectionProfile::PROTOCOL_UDP,
            ])
            ->orderBy('id')
            ->first();

        if ($profile === null) {
            return $summary;
        }

        $host = $profile->host ?? '127.0.0.1';
        $port = (int) ($profile->port ?? 10023);

        try {
            $connectivity = $this->capture->capture(
                'live_osc',
                fn (string $path): string => $this->oscClient->queryString($host, $port, $path),
                fn (string $path): int => $this->oscClient->queryInt($host, $port, $path),
            );

            $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];
            $routing['source_connectivity'] = $connectivity['normalized'];
            $routing['source_connectivity_meta'] = [
                'source' => 'live_osc',
                'read_at' => now()->toIso8601String(),
            ];
            $summary['routing'] = $routing;
        } catch (\Throwable $exception) {
            Log::debug('[routing-connectivity] Live stat read failed', [
                'device_id' => $device->id,
                'host' => $host,
                'port' => $port,
                'message' => $exception->getMessage(),
            ]);
        }

        return $summary;
    }
}
