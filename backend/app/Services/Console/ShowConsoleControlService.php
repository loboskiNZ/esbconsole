<?php

namespace App\Services\Console;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\X32\X32InputChannelControlMap;
use App\Services\X32\X32RuntimeModeResolver;
use Illuminate\Validation\ValidationException;

class ShowConsoleControlService
{
    public function __construct(
        private readonly ShowConsoleBaselineService $baselineService,
        private readonly ShowConsoleWorkspaceResolver $workspaceResolver,
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function updateControl(Show $show, int $channelNumber, string $controlKey, mixed $value): array
    {
        $definition = X32InputChannelControlMap::definition($controlKey);

        if ($definition === null) {
            throw ValidationException::withMessages([
                'control_key' => 'Unknown console control.',
            ]);
        }

        $channelNumber = X32InputChannelControlMap::clampChannel($channelNumber);
        $normalizedValue = $this->normalizeValue($definition, $value);

        $pendingSnapshot = $this->workspaceResolver->pendingSnapshotForShow($show);

        if ($pendingSnapshot !== null) {
            $device = $this->resolveDeviceFromSnapshot($pendingSnapshot);
            $this->persistControl($pendingSnapshot, null, $channelNumber, $controlKey, $definition, $normalizedValue, $device);

            return $this->resultPayload($controlKey, $normalizedValue, $channelNumber);
        }

        $baseline = $this->workspaceResolver->activeBaselineForShow($show);

        if ($baseline === null) {
            throw ValidationException::withMessages([
                'show' => 'No console data to update — learn a scene first.',
            ]);
        }

        $device = $this->resolveDeviceFromBaseline($baseline);
        $this->persistControl(null, $baseline, $channelNumber, $controlKey, $definition, $normalizedValue, $device);

        return $this->resultPayload($controlKey, $normalizedValue, $channelNumber);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normalizeValue(array $definition, mixed $value): float|bool
    {
        $type = (string) ($definition['value_type'] ?? 'float');

        if ($type === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $floatValue = (float) $value;
        $min = (float) ($definition['min'] ?? 0.0);
        $max = (float) ($definition['max'] ?? 1.0);

        return min($max, max($min, $floatValue));
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function persistControl(
        ?ConsoleLearningSnapshot $snapshot,
        ?ShowConsoleBaseline $baseline,
        int $channelNumber,
        string $controlKey,
        array $definition,
        float|bool $value,
        IntegrationDevice $device,
    ): void {
        $oscPath = X32InputChannelControlMap::oscPath($controlKey, $channelNumber);
        $canWriteOsc = ! empty($definition['write'])
            && empty($definition['ui_only'])
            && empty($definition['headamp_dependent'])
            && $oscPath !== null;

        if ($canWriteOsc) {
            $profile = $this->resolveOscProfile($device);
            $host = $profile->host ?? '127.0.0.1';
            $port = (int) ($profile->port ?? 10023);
            $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration ?? []);
            $oscValue = $this->toOscValue($definition, $value);
            $oscType = ($definition['value_type'] ?? 'float') === 'bool' ? 'int' : 'float';
            $this->dispatchOscWrite($runtimeMode, $host, $port, $oscPath, $oscType, $oscValue);
        }

        if ($snapshot !== null) {
            $this->baselineService->applyChannelControlToSnapshot($snapshot, $channelNumber, $controlKey, $value);
        } else {
            $this->baselineService->applyChannelControl($baseline, $channelNumber, $controlKey, $value);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function toOscValue(array $definition, float|bool $value): float|int
    {
        if (($definition['value_type'] ?? '') === 'bool') {
            $boolValue = (bool) $value;

            if (! empty($definition['invert_osc'])) {
                return $boolValue ? 0 : 1;
            }

            return $boolValue ? 1 : 0;
        }

        return (float) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultPayload(string $controlKey, float|bool $value, int $channelNumber): array
    {
        return [
            'control_key' => $controlKey,
            'channel' => $channelNumber,
            'value' => $value,
            'osc_path' => X32InputChannelControlMap::oscPath($controlKey, $channelNumber),
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
