<?php

namespace App\Services\X32;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use Illuminate\Support\Facades\Log;

/**
 * Reads operator-facing scene metadata from the X32 showfile over OSC.
 */
class X32SceneMetadataService
{
    public function __construct(
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
        private readonly X32SceneParameterResolver $sceneParameterResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function enrichSummaryWithSceneName(array $summary, ?IntegrationDevice $device): array
    {
        if ($this->resolveStoredSceneName($summary) !== null) {
            return $summary;
        }

        $operatorSceneNumber = $this->resolveOperatorSceneNumber($summary);

        if ($operatorSceneNumber === null || $device === null) {
            return $summary;
        }

        if (! $this->runtimeModeResolver->isLive(
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
            $sceneName = $this->readSceneName($host, $port, $operatorSceneNumber);

            if ($sceneName === null) {
                return $summary;
            }

            $summary['scene_name'] = $sceneName;

            $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];
            $routing['scene_name'] = $sceneName;
            $summary['routing'] = $routing;
        } catch (\Throwable $exception) {
            Log::debug('[routing-scene] Live showfile name read failed', [
                'device_id' => $device->id,
                'host' => $host,
                'port' => $port,
                'scene' => $operatorSceneNumber,
                'message' => $exception->getMessage(),
            ]);
        }

        return $summary;
    }

    public function readSceneName(string $host, int $port, int $operatorSceneNumber): ?string
    {
        $resolved = $this->sceneParameterResolver->resolve(['scene' => (string) $operatorSceneNumber]);

        if ($resolved === null) {
            return null;
        }

        $name = trim($this->oscClient->queryString(
            $host,
            $port,
            X32OscAddressMap::sceneShowfileName($resolved),
        ));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function resolveStoredSceneName(array $summary): ?string
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];

        foreach ([
            $summary['scene_name'] ?? null,
            $routing['scene_name'] ?? null,
        ] as $name) {
            $name = trim((string) ($name ?? ''));

            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function resolveOperatorSceneNumber(array $summary): ?int
    {
        $routing = is_array($summary['routing'] ?? null) ? $summary['routing'] : [];

        foreach ([
            $summary['scene_number'] ?? null,
            $summary['requested_scene_number'] ?? null,
            $routing['scene_recalled'] ?? null,
        ] as $candidate) {
            $resolved = $this->sceneParameterResolver->resolve([
                'scene' => is_scalar($candidate) ? (string) $candidate : null,
            ]);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        foreach ([
            $routing['scene_osc_index'] ?? null,
            $routing['scene_index'] ?? null,
        ] as $zeroBasedIndex) {
            if (! is_numeric($zeroBasedIndex)) {
                continue;
            }

            $resolved = $this->sceneParameterResolver->resolve([
                'scene' => (string) ((int) $zeroBasedIndex + 1),
            ]);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }
}
