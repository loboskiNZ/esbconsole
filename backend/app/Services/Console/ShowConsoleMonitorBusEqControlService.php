<?php

namespace App\Services\Console;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\X32\X32BusEqOscDecoder;
use App\Services\X32\X32MonitorBusEqControlMap;
use App\Services\X32\X32RuntimeModeResolver;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ShowConsoleMonitorBusEqControlService
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
    public function updateEq(Show $show, int $busNumber, ?int $bandNumber, string $parameter, mixed $value): array
    {
        $busNumber = X32MonitorBusEqControlMap::clampBus($busNumber);
        $bandNumber = $bandNumber !== null ? X32MonitorBusEqControlMap::clampBand($bandNumber) : null;

        if (! in_array($parameter, X32MonitorBusEqControlMap::allowedParameters(), true)) {
            throw ValidationException::withMessages([
                'parameter' => 'Unsupported monitor bus EQ parameter.',
            ]);
        }

        if ($parameter === X32MonitorBusEqControlMap::PARAMETER_ON && $bandNumber !== null) {
            throw ValidationException::withMessages([
                'band' => 'Band number is not used for bus EQ master on.',
            ]);
        }

        if ($parameter !== X32MonitorBusEqControlMap::PARAMETER_ON && $bandNumber === null) {
            throw ValidationException::withMessages([
                'band' => 'Band number is required for this EQ parameter.',
            ]);
        }

        $oscPath = X32MonitorBusEqControlMap::oscPath($busNumber, $parameter, $bandNumber);

        if ($oscPath === null) {
            throw ValidationException::withMessages([
                'parameter' => 'Unsupported monitor bus EQ parameter.',
            ]);
        }

        $device = $this->resolveDeviceForShow($show);
        $profile = $this->resolveOscProfile($device);
        $host = $profile->host ?? '127.0.0.1';
        $port = (int) ($profile->port ?? 10023);
        $runtimeMode = $this->runtimeModeResolver->resolve($device->configuration ?? []);

        if (! $this->runtimeModeResolver->isLive($runtimeMode)) {
            return $this->failurePayload(
                $busNumber,
                $bandNumber,
                $parameter,
                $oscPath,
                $value,
                null,
                null,
                'Live X32 control is not enabled for this console device.',
            );
        }

        try {
            return match ($parameter) {
                X32MonitorBusEqControlMap::PARAMETER_ON => $this->writeOn($host, $port, $busNumber, $oscPath, $value),
                X32MonitorBusEqControlMap::PARAMETER_TYPE => $this->writeType($host, $port, $busNumber, $bandNumber, $oscPath, $value),
                X32MonitorBusEqControlMap::PARAMETER_FREQUENCY => $this->writeFrequency($host, $port, $busNumber, $bandNumber, $oscPath, $value),
                X32MonitorBusEqControlMap::PARAMETER_GAIN => $this->writeGain($host, $port, $busNumber, $bandNumber, $oscPath, $value),
                X32MonitorBusEqControlMap::PARAMETER_Q => $this->writeQ($host, $port, $busNumber, $bandNumber, $oscPath, $value),
                default => $this->failurePayload(
                    $busNumber,
                    $bandNumber,
                    $parameter,
                    $oscPath,
                    $value,
                    null,
                    null,
                    'Unsupported monitor bus EQ parameter.',
                ),
            };
        } catch (InvalidArgumentException $exception) {
            return $this->failurePayload(
                $busNumber,
                $bandNumber,
                $parameter,
                $oscPath,
                $value,
                null,
                null,
                $exception->getMessage(),
            );
        } catch (Throwable $exception) {
            return $this->failurePayload(
                $busNumber,
                $bandNumber,
                $parameter,
                $oscPath,
                $value,
                null,
                null,
                'Monitor bus EQ write failed: '.$exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function writeOn(string $host, int $port, int $busNumber, string $oscPath, mixed $value): array
    {
        $requestedOn = X32MonitorBusEqControlMap::onFromRequest($value);

        $this->oscClient->setInt($host, $port, $oscPath, $requestedOn);

        $confirmedOn = $this->confirmOnAfterWrite($host, $port, $oscPath, $requestedOn);

        if ($confirmedOn !== $requestedOn) {
            return $this->failurePayload(
                $busNumber,
                null,
                X32MonitorBusEqControlMap::PARAMETER_ON,
                $oscPath,
                X32MonitorBusEqControlMap::onToEnabled($requestedOn),
                $requestedOn,
                $confirmedOn,
                'Monitor bus EQ on write was not confirmed by the console.',
            );
        }

        return $this->successPayload(
            $busNumber,
            null,
            X32MonitorBusEqControlMap::PARAMETER_ON,
            $oscPath,
            X32MonitorBusEqControlMap::onToEnabled($requestedOn),
            $requestedOn,
            $confirmedOn,
            X32MonitorBusEqControlMap::onToEnabled($confirmedOn) ? 'ON' : 'OFF',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function writeType(string $host, int $port, int $busNumber, int $bandNumber, string $oscPath, mixed $value): array
    {
        $requestedType = X32MonitorBusEqControlMap::typeFromRequest($value);

        $this->oscClient->setInt($host, $port, $oscPath, $requestedType);

        $confirmedType = $this->oscClient->queryInt($host, $port, $oscPath);

        if ($confirmedType !== $requestedType) {
            return $this->failurePayload(
                $busNumber,
                $bandNumber,
                X32MonitorBusEqControlMap::PARAMETER_TYPE,
                $oscPath,
                X32BusEqOscDecoder::typeToMode($requestedType),
                $requestedType,
                $confirmedType,
                'Monitor bus EQ type write was not confirmed by the console.',
            );
        }

        return $this->successPayload(
            $busNumber,
            $bandNumber,
            X32MonitorBusEqControlMap::PARAMETER_TYPE,
            $oscPath,
            X32BusEqOscDecoder::typeToMode($requestedType),
            $requestedType,
            $confirmedType,
            (string) X32BusEqOscDecoder::typeToMode($confirmedType),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function writeFrequency(string $host, int $port, int $busNumber, int $bandNumber, string $oscPath, mixed $value): array
    {
        $requestedHz = X32MonitorBusEqControlMap::frequencyHzFromRequest($value);
        $encoded = X32BusEqOscDecoder::encodeFrequency($requestedHz);

        $this->oscClient->setFloat($host, $port, $oscPath, $encoded);

        $confirmedEncoded = $this->oscClient->queryFloat($host, $port, $oscPath);

        if (! X32MonitorBusEqControlMap::normalizedValuesMatch($encoded, $confirmedEncoded)) {
            return $this->failurePayload(
                $busNumber,
                $bandNumber,
                X32MonitorBusEqControlMap::PARAMETER_FREQUENCY,
                $oscPath,
                $requestedHz,
                $encoded,
                $confirmedEncoded,
                'Monitor bus EQ frequency write was not confirmed by the console.',
            );
        }

        $confirmedHz = X32BusEqOscDecoder::decodeFrequency($confirmedEncoded) ?? $requestedHz;

        return $this->successPayload(
            $busNumber,
            $bandNumber,
            X32MonitorBusEqControlMap::PARAMETER_FREQUENCY,
            $oscPath,
            $requestedHz,
            $encoded,
            $confirmedEncoded,
            X32MonitorBusEqControlMap::frequencyDisplay($confirmedHz),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function writeGain(string $host, int $port, int $busNumber, int $bandNumber, string $oscPath, mixed $value): array
    {
        $requestedDb = X32MonitorBusEqControlMap::gainDbFromRequest($value);
        $encoded = X32BusEqOscDecoder::encodeGainDb($requestedDb);

        $this->oscClient->setFloat($host, $port, $oscPath, $encoded);

        $confirmedEncoded = $this->oscClient->queryFloat($host, $port, $oscPath);

        if (! X32MonitorBusEqControlMap::normalizedValuesMatch($encoded, $confirmedEncoded)) {
            return $this->failurePayload(
                $busNumber,
                $bandNumber,
                X32MonitorBusEqControlMap::PARAMETER_GAIN,
                $oscPath,
                $requestedDb,
                $encoded,
                $confirmedEncoded,
                'Monitor bus EQ gain write was not confirmed by the console.',
            );
        }

        $confirmedDb = X32BusEqOscDecoder::decodeGainDb($confirmedEncoded) ?? $requestedDb;

        return $this->successPayload(
            $busNumber,
            $bandNumber,
            X32MonitorBusEqControlMap::PARAMETER_GAIN,
            $oscPath,
            $requestedDb,
            $encoded,
            $confirmedEncoded,
            X32MonitorBusEqControlMap::gainDisplay($confirmedDb),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function writeQ(string $host, int $port, int $busNumber, int $bandNumber, string $oscPath, mixed $value): array
    {
        $requestedQ = X32MonitorBusEqControlMap::qFromRequest($value);
        $encoded = X32BusEqOscDecoder::encodeQ($requestedQ);

        $this->oscClient->setFloat($host, $port, $oscPath, $encoded);

        $confirmedEncoded = $this->oscClient->queryFloat($host, $port, $oscPath);

        if (! X32MonitorBusEqControlMap::normalizedValuesMatch($encoded, $confirmedEncoded)) {
            return $this->failurePayload(
                $busNumber,
                $bandNumber,
                X32MonitorBusEqControlMap::PARAMETER_Q,
                $oscPath,
                $requestedQ,
                $encoded,
                $confirmedEncoded,
                'Monitor bus EQ Q write was not confirmed by the console.',
            );
        }

        $confirmedQ = X32BusEqOscDecoder::decodeQ($confirmedEncoded) ?? $requestedQ;

        return $this->successPayload(
            $busNumber,
            $bandNumber,
            X32MonitorBusEqControlMap::PARAMETER_Q,
            $oscPath,
            $requestedQ,
            $encoded,
            $confirmedEncoded,
            X32MonitorBusEqControlMap::qDisplay($confirmedQ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function successPayload(
        int $busNumber,
        ?int $bandNumber,
        string $parameter,
        string $oscPath,
        mixed $requestedValue,
        mixed $encodedOscValue,
        mixed $confirmedValue,
        string $displayValue,
    ): array {
        return [
            'success' => true,
            'bus' => $busNumber,
            'band' => $bandNumber,
            'parameter' => $parameter,
            'osc_path' => $oscPath,
            'requested_value' => $requestedValue,
            'encoded_osc_value' => $encodedOscValue,
            'confirmed_value' => $confirmedValue,
            'display_value' => $displayValue,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failurePayload(
        int $busNumber,
        ?int $bandNumber,
        string $parameter,
        string $oscPath,
        mixed $requestedValue,
        mixed $encodedOscValue,
        mixed $confirmedValue,
        string $error,
    ): array {
        return [
            'success' => false,
            'bus' => $busNumber,
            'band' => $bandNumber,
            'parameter' => $parameter,
            'osc_path' => $oscPath,
            'requested_value' => $requestedValue,
            'encoded_osc_value' => $encodedOscValue,
            'confirmed_value' => $confirmedValue,
            'display_value' => $this->displayValueForFailure($parameter, $confirmedValue),
            'error' => $error,
        ];
    }

    private function displayValueForFailure(string $parameter, mixed $confirmedValue): string
    {
        return match ($parameter) {
            X32MonitorBusEqControlMap::PARAMETER_ON => is_int($confirmedValue)
                ? (X32MonitorBusEqControlMap::onToEnabled($confirmedValue) ? 'ON' : 'OFF')
                : '—',
            X32MonitorBusEqControlMap::PARAMETER_TYPE => is_int($confirmedValue)
                ? (string) (X32BusEqOscDecoder::typeToMode($confirmedValue) ?? '—')
                : '—',
            X32MonitorBusEqControlMap::PARAMETER_FREQUENCY => is_float($confirmedValue) || is_int($confirmedValue)
                ? X32MonitorBusEqControlMap::frequencyDisplay(
                    X32BusEqOscDecoder::decodeFrequency((float) $confirmedValue) ?? 0.0,
                )
                : '—',
            X32MonitorBusEqControlMap::PARAMETER_GAIN => is_float($confirmedValue) || is_int($confirmedValue)
                ? X32MonitorBusEqControlMap::gainDisplay(
                    X32BusEqOscDecoder::decodeGainDb((float) $confirmedValue) ?? 0.0,
                )
                : '—',
            X32MonitorBusEqControlMap::PARAMETER_Q => is_float($confirmedValue) || is_int($confirmedValue)
                ? X32MonitorBusEqControlMap::qDisplay(
                    X32BusEqOscDecoder::decodeQ((float) $confirmedValue) ?? 0.0,
                )
                : '—',
            default => '—',
        };
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

    private function confirmOnAfterWrite(string $host, int $port, string $oscPath, int $requestedOn): int
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
