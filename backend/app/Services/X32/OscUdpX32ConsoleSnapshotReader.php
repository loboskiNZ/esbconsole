<?php

namespace App\Services\X32;

use App\Contracts\X32\X32ConsoleSnapshotReaderInterface;
use App\Contracts\X32\X32OscConsoleClientInterface;
use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\DataTransferObjects\X32\X32ConsoleLearnResult;
use App\Enums\ConsoleType;
use App\Models\IntegrationDevice;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recalls a scene on a live X32/M32 desk and reads channel state over OSC/UDP.
 */
class OscUdpX32ConsoleSnapshotReader implements X32ConsoleSnapshotReaderInterface
{
    private const XREMOTE_REFRESH_QUERY_INTERVAL = 40;

    private int $queryCount = 0;

    public function __construct(
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32OscMessageCodec $codec,
        private readonly X32OscSceneRecallPacketBuilder $sceneRecallBuilder,
        private readonly X32SceneParameterResolver $sceneParameterResolver,
        private readonly X32RoutingLearnCapture $routingLearnCapture,
        private readonly X32SourceConnectivityCapture $sourceConnectivityCapture,
        private readonly int $sceneSettleMs = 800,
    ) {}

    public function learnScene(X32ConsoleLearnCommand $command): X32ConsoleLearnResult
    {
        $sceneNumber = $this->sceneParameterResolver->resolve([
            'scene' => $command->requestedSceneNumber,
        ]);

        if ($sceneNumber === null) {
            return new X32ConsoleLearnResult(
                success: false,
                summary: [],
                rawSnapshot: [],
                errors: ['Scene number must be between 1 and 100.'],
            );
        }

        $sceneLabel = str_pad($sceneNumber, 2, '0', STR_PAD_LEFT);
        $consoleType = $this->resolveConsoleType($command->device);
        $oscResponses = [];
        $this->queryCount = 0;

        $this->logLearnDebug('START LEARN', [
            'host' => $command->host,
            'port' => $command->port,
            'device_key' => $command->device->device_key,
            'scene' => $sceneLabel,
        ]);

        try {
            $this->sendXremote($command);

            $this->logLearnDebug('OSC scene recall', [
                'path' => X32OscAddressMap::sceneRecall(),
                'scene_index' => (int) $sceneNumber - 1,
            ]);

            $this->oscClient->sendPacket(
                $command->host,
                $command->port,
                $this->sceneRecallBuilder->build($sceneNumber),
            );

            usleep($this->sceneSettleMs * 1000);

            $channels = $this->readChannels($command, $oscResponses);
            $buses = $this->readBuses($command, $oscResponses);
            $dcas = $this->readDcas($command, $oscResponses);
            $matrices = $this->readMatrices($command, $oscResponses);
            $routing = $this->readRouting($command, $oscResponses);
            $sceneName = $this->readSceneName($command, (int) $sceneNumber, $oscResponses);

            $summary = [
                'transport' => 'live_osc',
                'console_type' => $consoleType->value,
                'device_key' => $command->device->device_key,
                'device_name' => $command->device->name,
                'requested_scene_number' => $command->requestedSceneNumber,
                'scene_number' => $sceneLabel,
                'scene_name' => $sceneName,
                'channels' => $channels,
                'buses' => $buses,
                'dcas' => $dcas,
                'matrices' => $matrices,
                'fx' => [],
                'routing' => array_merge($routing, [
                    'host' => $command->host,
                    'port' => $command->port,
                    'scene_recalled' => (int) $sceneNumber,
                    'scene_name' => $sceneName,
                ]),
            ];

            $this->logLearnDebug('LEARN COMPLETE', [
                'host' => $command->host,
                'port' => $command->port,
                'osc_queries' => $this->queryCount,
                'osc_responses' => count($oscResponses),
            ]);

            return new X32ConsoleLearnResult(
                success: true,
                summary: $summary,
                rawSnapshot: [
                    'transport' => 'live_osc',
                    'host' => $command->host,
                    'port' => $command->port,
                    'device_key' => $command->device->device_key,
                    'requested_scene_number' => $command->requestedSceneNumber,
                    'scene_recalled' => (int) $sceneNumber,
                    'osc_responses' => $oscResponses,
                ],
                warnings: array_values(array_filter([
                    sprintf('Scene %s recalled on %s:%d and read over live OSC.', $sceneLabel, $command->host, $command->port),
                    'FX slot reads are not yet implemented.',
                    ...($routing['warnings'] ?? []),
                ])),
                errors: [],
            );
        } catch (Throwable $exception) {
            $this->logLearnDebug('LEARN FAILED', [
                'host' => $command->host,
                'port' => $command->port,
                'osc_queries' => $this->queryCount,
                'osc_responses' => count($oscResponses),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return new X32ConsoleLearnResult(
                success: false,
                summary: [],
                rawSnapshot: [
                    'transport' => 'live_osc',
                    'host' => $command->host,
                    'port' => $command->port,
                    'osc_responses' => $oscResponses,
                ],
                warnings: [],
                errors: [
                    sprintf(
                        'Live OSC console learning failed (%s:%d): %s',
                        $command->host,
                        $command->port,
                        $exception->getMessage(),
                    ),
                ],
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     * @return array<int, array<string, mixed>>
     */
    private function readChannels(X32ConsoleLearnCommand $command, array &$oscResponses): array
    {
        $channels = [];

        for ($index = 1; $index <= 32; $index++) {
            $faderPath = X32InputChannelControlMap::oscPath('fader', $index);
            $onPath = X32InputChannelControlMap::oscPath('mute', $index);
            $namePath = X32OscAddressMap::channelName($index);
            $colorPath = X32OscAddressMap::channelColor($index);
            $gatePath = X32InputChannelControlMap::oscPath('gate_on', $index);
            $dynPath = X32InputChannelControlMap::oscPath('compressor_on', $index);
            $eqPath = X32InputChannelControlMap::oscPath('eq_on', $index);
            $panPath = X32InputChannelControlMap::oscPath('pan', $index);
            $mainPath = X32InputChannelControlMap::oscPath('main_lr', $index);

            $fader = $this->queryFloat($command, $faderPath, $oscResponses);
            $on = $this->queryInt($command, $onPath, $oscResponses);
            $name = trim($this->queryString($command, $namePath, $oscResponses));
            $color = $this->queryInt($command, $colorPath, $oscResponses);
            $gateOn = $this->queryInt($command, $gatePath, $oscResponses);
            $dynOn = $this->queryInt($command, $dynPath, $oscResponses);
            $eqOn = $this->queryInt($command, $eqPath, $oscResponses);
            $pan = $this->queryFloat($command, $panPath, $oscResponses);
            $mainSt = $this->queryInt($command, $mainPath, $oscResponses);

            $channels[] = [
                'index' => $index,
                'name' => $name !== '' ? $name : sprintf('CH %02d', $index),
                'color' => $color,
                'fader' => round($fader, 4),
                'mute' => $on === 0,
                'osc_fader' => $faderPath,
                'osc_on' => $onPath,
                'controls' => [
                    'gate_on' => $gateOn === 1,
                    'compressor_on' => $dynOn === 1,
                    'eq_on' => $eqOn === 1,
                    'pan' => round($pan, 4),
                    'main_lr' => $mainSt === 1,
                ],
            ];
        }

        return $channels;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     * @return array<int, array<string, mixed>>
     */
    private function readBuses(X32ConsoleLearnCommand $command, array &$oscResponses): array
    {
        $buses = [];

        for ($index = 1; $index <= 16; $index++) {
            $faderPath = X32OscAddressMap::busFader($index);
            $onPath = X32OscAddressMap::busOn($index);
            $namePath = X32OscAddressMap::busName($index);
            $colorPath = X32OscAddressMap::busColor($index);

            $fader = $this->queryFloat($command, $faderPath, $oscResponses);
            $on = $this->queryInt($command, $onPath, $oscResponses);
            $name = trim($this->queryString($command, $namePath, $oscResponses));
            $color = $this->queryInt($command, $colorPath, $oscResponses);

            $buses[] = [
                'index' => $index,
                'name' => $name !== '' ? $name : sprintf('Bus %02d', $index),
                'color' => $color,
                'fader' => round($fader, 4),
                'mute' => $on === 0,
                'osc_fader' => $faderPath,
                'osc_on' => $onPath,
            ];
        }

        return $buses;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     * @return array<int, array<string, mixed>>
     */
    private function readDcas(X32ConsoleLearnCommand $command, array &$oscResponses): array
    {
        $dcas = [];

        for ($index = 1; $index <= 8; $index++) {
            $faderPath = X32OscAddressMap::dcaFader($index);
            $onPath = X32OscAddressMap::dcaOn($index);

            $fader = $this->queryFloat($command, $faderPath, $oscResponses);
            $on = $this->queryInt($command, $onPath, $oscResponses);

            $dcas[] = [
                'index' => $index,
                'name' => sprintf('DCA %d', $index),
                'fader' => round($fader, 4),
                'mute' => $on === 0,
                'osc_fader' => $faderPath,
                'osc_on' => $onPath,
            ];
        }

        return $dcas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     * @return array<int, array<string, mixed>>
     */
    private function readMatrices(X32ConsoleLearnCommand $command, array &$oscResponses): array
    {
        $matrices = [];

        for ($index = 1; $index <= 6; $index++) {
            $faderPath = X32OscAddressMap::matrixFader($index);
            $onPath = X32OscAddressMap::matrixOn($index);

            $fader = $this->queryFloat($command, $faderPath, $oscResponses);
            $on = $this->queryInt($command, $onPath, $oscResponses);

            $matrices[] = [
                'index' => $index,
                'name' => sprintf('MTRX %d', $index),
                'fader' => round($fader, 4),
                'mute' => $on === 0,
                'osc_fader' => $faderPath,
                'osc_on' => $onPath,
            ];
        }

        return $matrices;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     * @return array<string, mixed>
     */
    private function readRouting(X32ConsoleLearnCommand $command, array &$oscResponses): array
    {
        $routing = $this->routingLearnCapture->capture(
            'live_osc',
            fn (string $path): int => $this->queryInt($command, $path, $oscResponses),
        );

        $connectivity = $this->sourceConnectivityCapture->capture(
            'live_osc',
            fn (string $path): string => $this->queryString($command, $path, $oscResponses),
            fn (string $path): int => $this->queryInt($command, $path, $oscResponses),
        );

        return array_merge($routing, [
            'source_connectivity' => $connectivity['normalized'],
            'source_connectivity_raw' => $connectivity['raw_osc'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     */
    private function readSceneName(
        X32ConsoleLearnCommand $command,
        int $operatorSceneNumber,
        array &$oscResponses,
    ): ?string {
        $path = X32OscAddressMap::sceneShowfileName($operatorSceneNumber);
        $name = trim($this->queryString($command, $path, $oscResponses));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     */
    private function queryFloat(X32ConsoleLearnCommand $command, string $path, array &$oscResponses): float
    {
        $this->refreshXremoteIfNeeded($command);
        $value = $this->oscClient->queryFloat($command->host, $command->port, $path);
        $oscResponses[] = ['path' => $path, 'value' => $value];
        $this->queryCount++;

        return $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     */
    private function queryInt(X32ConsoleLearnCommand $command, string $path, array &$oscResponses): int
    {
        $this->refreshXremoteIfNeeded($command);
        $value = $this->oscClient->queryInt($command->host, $command->port, $path);
        $oscResponses[] = ['path' => $path, 'value' => $value];
        $this->queryCount++;

        return $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $oscResponses
     */
    private function queryString(X32ConsoleLearnCommand $command, string $path, array &$oscResponses): string
    {
        $this->refreshXremoteIfNeeded($command);
        $value = $this->oscClient->queryString($command->host, $command->port, $path);
        $oscResponses[] = ['path' => $path, 'value' => $value];
        $this->queryCount++;

        return $value;
    }

    private function sendXremote(X32ConsoleLearnCommand $command): void
    {
        $this->oscClient->sendPacket(
            $command->host,
            $command->port,
            $this->codec->buildXremote(),
        );

        $this->logLearnDebug('OSC xremote sent', [
            'path' => '/xremote',
            'host' => $command->host,
            'port' => $command->port,
        ]);
    }

    private function refreshXremoteIfNeeded(X32ConsoleLearnCommand $command): void
    {
        if ($this->queryCount > 0 && $this->queryCount % self::XREMOTE_REFRESH_QUERY_INTERVAL === 0) {
            $this->sendXremote($command);
        }
    }

    private function resolveConsoleType(IntegrationDevice $device): ConsoleType
    {
        $model = mb_strtolower((string) ($device->configuration['console_model'] ?? ''));

        return $model === ConsoleType::M32->value ? ConsoleType::M32 : ConsoleType::X32;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logLearnDebug(string $message, array $context = []): void
    {
        if (! config('services.console_learn.osc_debug', false)) {
            return;
        }

        Log::info('[console-learn] '.$message, $context);
    }
}
