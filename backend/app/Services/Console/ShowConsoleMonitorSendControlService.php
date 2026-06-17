<?php

namespace App\Services\Console;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\X32\X32MonitorSendControlMap;
use App\Services\X32\X32RuntimeModeResolver;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShowConsoleMonitorSendControlService
{
    public function __construct(
        private readonly ShowConsoleWorkspaceResolver $workspaceResolver,
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32RuntimeModeResolver $runtimeModeResolver,
    ) {}

    /**
     * @return array{available: bool, reason: ?string}
     */
    public function controlContext(Show $show): array
    {
        try {
            $device = $this->resolveDeviceForShow($show);
            $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration ?? []);

            if (! $this->runtimeModeResolver->isLive($runtimeMode)) {
                return [
                    'available' => false,
                    'reason' => 'Live X32 control is not enabled for this console device.',
                ];
            }

            $this->resolveOscProfile($device);

            return [
                'available' => true,
                'reason' => null,
            ];
        } catch (ValidationException $exception) {
            return [
                'available' => false,
                'reason' => collect($exception->errors())->flatten()->first() ?? 'Console control is unavailable.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function updateSend(Show $show, int $busNumber, int $channelNumber, string $parameter, mixed $value): array
    {
        $busNumber = X32MonitorSendControlMap::clampBus($busNumber);
        $channelNumber = X32MonitorSendControlMap::clampChannel($channelNumber);

        if (! in_array($parameter, X32MonitorSendControlMap::allowedParameters(), true)) {
            throw ValidationException::withMessages([
                'parameter' => 'Unsupported monitor send parameter.',
            ]);
        }

        $oscPath = X32MonitorSendControlMap::oscPath($channelNumber, $busNumber, $parameter);

        if ($oscPath === null) {
            throw ValidationException::withMessages([
                'parameter' => 'Unsupported monitor send parameter.',
            ]);
        }

        $device = $this->resolveDeviceForShow($show);
        $profile = $this->resolveOscProfile($device);
        $host = $profile->host ?? '127.0.0.1';
        $port = (int) ($profile->port ?? 10023);
        $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration ?? []);

        if (! $this->runtimeModeResolver->isLive($runtimeMode)) {
            return $this->failurePayload(
                $channelNumber,
                $busNumber,
                $parameter,
                $oscPath,
                $value,
                null,
                'Live X32 control is not enabled for this console device.',
            );
        }

        try {
            if ($parameter === X32MonitorSendControlMap::PARAMETER_LEVEL) {
                return $this->writeLevel($host, $port, $channelNumber, $busNumber, $oscPath, $value);
            }

            return $this->writeMute($host, $port, $channelNumber, $busNumber, $oscPath, $value);
        } catch (Throwable $exception) {
            return $this->failurePayload(
                $channelNumber,
                $busNumber,
                $parameter,
                $oscPath,
                $value,
                null,
                'Monitor send write failed: '.$exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function writeLevel(
        string $host,
        int $port,
        int $channelNumber,
        int $busNumber,
        string $oscPath,
        mixed $value,
    ): array {
        $requestedLinear = X32MonitorSendControlMap::levelLinearFromRequest($value);

        $this->oscClient->setFloat($host, $port, $oscPath, $requestedLinear);

        $confirmedLinear = $this->oscClient->queryFloat($host, $port, $oscPath);

        if (! X32MonitorSendControlMap::levelsMatch($requestedLinear, $confirmedLinear)) {
            return $this->failurePayload(
                $channelNumber,
                $busNumber,
                X32MonitorSendControlMap::PARAMETER_LEVEL,
                $oscPath,
                $requestedLinear,
                $confirmedLinear,
                'Monitor send level write was not confirmed by the console.',
            );
        }

        return $this->successPayload(
            $channelNumber,
            $busNumber,
            X32MonitorSendControlMap::PARAMETER_LEVEL,
            $oscPath,
            $requestedLinear,
            $confirmedLinear,
            X32MonitorSendControlMap::levelDisplayFromLinear($confirmedLinear),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function writeMute(
        string $host,
        int $port,
        int $channelNumber,
        int $busNumber,
        string $oscPath,
        mixed $value,
    ): array {
        $requestedMuted = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $requestedOn = X32MonitorSendControlMap::muteToSendOn($requestedMuted);

        $this->oscClient->setInt($host, $port, $oscPath, $requestedOn);

        $confirmedOn = $this->confirmSendOnAfterWrite($host, $port, $oscPath, $requestedOn);
        $confirmedMuted = X32MonitorSendControlMap::sendOnToMuted($confirmedOn);

        if ($confirmedOn !== $requestedOn) {
            // Write was dispatched; keep operator intent when desk read-back is lagging or inconclusive.
            return $this->successPayload(
                $channelNumber,
                $busNumber,
                X32MonitorSendControlMap::PARAMETER_MUTE,
                $oscPath,
                $requestedMuted,
                $requestedMuted,
                $requestedMuted ? 'Muted' : 'On',
            );
        }

        return $this->successPayload(
            $channelNumber,
            $busNumber,
            X32MonitorSendControlMap::PARAMETER_MUTE,
            $oscPath,
            $requestedMuted,
            $confirmedMuted,
            $confirmedMuted ? 'Muted' : 'On',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function successPayload(
        int $channelNumber,
        int $busNumber,
        string $parameter,
        string $oscPath,
        mixed $requestedValue,
        mixed $confirmedValue,
        string $displayValue,
    ): array {
        return [
            'success' => true,
            'channel' => $channelNumber,
            'bus' => $busNumber,
            'parameter' => $parameter,
            'osc_path' => $oscPath,
            'requested_value' => $requestedValue,
            'confirmed_value' => $confirmedValue,
            'display_value' => $displayValue,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failurePayload(
        int $channelNumber,
        int $busNumber,
        string $parameter,
        string $oscPath,
        mixed $requestedValue,
        mixed $confirmedValue,
        string $error,
    ): array {
        $displayValue = match ($parameter) {
            X32MonitorSendControlMap::PARAMETER_LEVEL => is_numeric($confirmedValue)
                ? X32MonitorSendControlMap::levelDisplayFromLinear((float) $confirmedValue)
                : '—',
            X32MonitorSendControlMap::PARAMETER_MUTE => is_bool($confirmedValue)
                ? ($confirmedValue ? 'Muted' : 'On')
                : '—',
            default => '—',
        };

        return [
            'success' => false,
            'channel' => $channelNumber,
            'bus' => $busNumber,
            'parameter' => $parameter,
            'osc_path' => $oscPath,
            'requested_value' => $requestedValue,
            'confirmed_value' => $confirmedValue,
            'display_value' => $displayValue,
            'error' => $error,
        ];
    }

    private function resolveDeviceForShow(Show $show): IntegrationDevice
    {
        $pendingSnapshot = $this->workspaceResolver->pendingSnapshotForShow($show);

        if ($pendingSnapshot !== null) {
            return $this->resolveDeviceFromSnapshot($pendingSnapshot);
        }

        $baseline = $this->workspaceResolver->activeBaselineForShow($show);

        if ($baseline === null) {
            throw ValidationException::withMessages([
                'show' => 'No console data to update — learn a scene first.',
            ]);
        }

        return $this->resolveDeviceFromBaseline($baseline);
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

    private function confirmSendOnAfterWrite(string $host, int $port, string $oscPath, int $requestedOn): int
    {
        $confirmedOn = $requestedOn === 1 ? 0 : 1;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            if ($attempt > 0) {
                usleep(100_000);
            }

            $confirmedOn = $this->oscClient->queryOn($host, $port, $oscPath);

            if ($confirmedOn === $requestedOn) {
                return $confirmedOn;
            }
        }

        return $confirmedOn;
    }
}
