<?php

namespace App\Services\Console;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\X32\X32OscAddressMap;
use App\Services\X32\X32RuntimeModeResolver;
use Illuminate\Validation\ValidationException;

class ShowConsoleParameterService
{
    public function __construct(
        private readonly ShowConsoleBaselineService $baselineService,
        private readonly ShowConsoleWorkspaceResolver $workspaceResolver,
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
    ) {}

    /**
     * @return array{fader: ?float, mute: ?bool, osc_path: string}
     */
    public function updateParameter(Show $show, string $oscPath, string $parameter, mixed $value): array
    {
        $parsed = X32OscAddressMap::parsePath($oscPath);

        if ($parsed === null) {
            throw ValidationException::withMessages([
                'osc_path' => 'Unsupported OSC path for console workspace control.',
            ]);
        }

        if ($parsed['parameter'] !== $parameter) {
            throw ValidationException::withMessages([
                'parameter' => 'Parameter does not match OSC path.',
            ]);
        }

        $pendingSnapshot = $this->workspaceResolver->pendingSnapshotForShow($show);

        if ($pendingSnapshot !== null) {
            $device = $this->resolveDeviceFromSnapshot($pendingSnapshot);
            $this->persistParameter($pendingSnapshot, null, $parsed, $parameter, $value, $device, $oscPath);

            return $this->resultPayload($parameter, $value, $oscPath);
        }

        $baseline = $this->workspaceResolver->activeBaselineForShow($show);

        if ($baseline === null) {
            throw ValidationException::withMessages([
                'show' => 'No console data to update — learn a scene first.',
            ]);
        }

        $device = $this->resolveDeviceFromBaseline($baseline);
        $this->persistParameter(null, $baseline, $parsed, $parameter, $value, $device, $oscPath);

        return $this->resultPayload($parameter, $value, $oscPath);
    }

    /**
     * @param  array{layer: string, index: int, parameter: string}  $parsed
     */
    private function persistParameter(
        ?ConsoleLearningSnapshot $snapshot,
        ?ShowConsoleBaseline $baseline,
        array $parsed,
        string $parameter,
        mixed $value,
        IntegrationDevice $device,
        string $oscPath,
    ): void {
        $profile = $this->resolveOscProfile($device);
        $host = $profile->host ?? '127.0.0.1';
        $port = (int) ($profile->port ?? 10023);
        $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration ?? []);

        if ($parameter === 'fader') {
            $floatValue = min(1.0, max(0.0, (float) $value));
            $this->dispatchOscWrite($runtimeMode, $host, $port, $oscPath, 'float', $floatValue);

            if ($snapshot !== null) {
                $this->baselineService->applyOscValueToSnapshot($snapshot, $parsed, 'fader', $floatValue);
            } else {
                $this->baselineService->applyOscValue($baseline, $parsed, 'fader', $floatValue);
            }

            return;
        }

        $mute = (bool) $value;
        $onValue = $mute ? 0 : 1;
        $this->dispatchOscWrite($runtimeMode, $host, $port, $oscPath, 'int', $onValue);

        if ($snapshot !== null) {
            $this->baselineService->applyOscValueToSnapshot($snapshot, $parsed, 'mute', $mute);
        } else {
            $this->baselineService->applyOscValue($baseline, $parsed, 'mute', $mute);
        }
    }

    /**
     * @return array{fader: ?float, mute: ?bool, osc_path: string}
     */
    private function resultPayload(string $parameter, mixed $value, string $oscPath): array
    {
        if ($parameter === 'fader') {
            return [
                'fader' => min(1.0, max(0.0, (float) $value)),
                'mute' => null,
                'osc_path' => $oscPath,
            ];
        }

        return [
            'fader' => null,
            'mute' => (bool) $value,
            'osc_path' => $oscPath,
        ];
    }

    private function resolveDeviceFromSnapshot(ConsoleLearningSnapshot $snapshot): IntegrationDevice
    {
        $device = $snapshot->integrationDevice;

        if ($device === null || ! $device->enabled) {
            throw ValidationException::withMessages([
                'console' => 'Console device is not available for this learning snapshot.',
            ]);
        }

        return $device;
    }

    private function resolveDeviceFromBaseline(ShowConsoleBaseline $baseline): IntegrationDevice
    {
        $device = $baseline->sourceSnapshot?->integrationDevice;

        if ($device === null || ! $device->enabled) {
            throw ValidationException::withMessages([
                'console' => 'Console device is not available for this show baseline.',
            ]);
        }

        return $device;
    }

    private function resolveOscProfile(IntegrationDevice $device): IntegrationConnectionProfile
    {
        $profile = $device->integrationConnectionProfiles()
            ->where('enabled', true)
            ->whereIn('protocol', [
                IntegrationConnectionProfile::PROTOCOL_OSC,
                IntegrationConnectionProfile::PROTOCOL_UDP,
            ])
            ->orderBy('id')
            ->first();

        if ($profile === null) {
            throw ValidationException::withMessages([
                'console' => 'Console device has no enabled OSC profile.',
            ]);
        }

        return $profile;
    }

    private function dispatchOscWrite(
        string $runtimeMode,
        string $host,
        int $port,
        string $path,
        string $type,
        float|int $value,
    ): void {
        if ($runtimeMode === X32RuntimeModeResolver::MODE_DISABLED) {
            return;
        }

        if ($type === 'float') {
            $this->oscClient->setFloat($host, $port, $path, (float) $value);

            return;
        }

        $this->oscClient->setInt($host, $port, $path, (int) $value);
    }
}
