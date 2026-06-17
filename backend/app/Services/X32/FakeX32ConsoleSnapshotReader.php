<?php

namespace App\Services\X32;

use App\Contracts\X32\X32ConsoleSnapshotReaderInterface;
use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\DataTransferObjects\X32\X32ConsoleLearnResult;
use App\Enums\ConsoleType;
use App\Models\IntegrationDevice;

/**
 * Fixture-based console snapshot reader for learning workflows and automated tests.
 *
 * Does not open network sockets. Returns representative channel/bus/DCA/matrix/FX data
 * plus a raw OSC-style response envelope for persistence and review.
 */
class FakeX32ConsoleSnapshotReader implements X32ConsoleSnapshotReaderInterface
{
    public function __construct(
        private readonly bool $shouldFail = false,
        private readonly ?string $failureMessage = null,
        private readonly X32RoutingLearnCapture $routingLearnCapture = new X32RoutingLearnCapture,
        private readonly X32SourceConnectivityCapture $sourceConnectivityCapture = new X32SourceConnectivityCapture,
    ) {}

    public function learnScene(X32ConsoleLearnCommand $command): X32ConsoleLearnResult
    {
        if ($this->shouldFail) {
            return new X32ConsoleLearnResult(
                success: false,
                summary: [],
                rawSnapshot: [],
                warnings: [],
                errors: [$this->failureMessage ?? 'Simulated console learning failure.'],
            );
        }

        $scene = $this->normalizeSceneNumber($command->requestedSceneNumber);
        $sceneSeed = $this->sceneSeed($scene);
        $consoleType = $this->resolveConsoleType($command->device);

        $channels = $this->buildChannels($scene, $sceneSeed);
        $buses = $this->buildBuses($sceneSeed);
        $dcas = $this->buildDcas($sceneSeed);
        $matrices = $this->buildMatrices($sceneSeed);
        $fx = $this->buildFxSlots();
        $routing = $this->buildRouting($command, $sceneSeed);

        $summary = [
            'transport' => 'fake_fixture',
            'console_type' => $consoleType->value,
            'device_key' => $command->device->device_key,
            'device_name' => $command->device->name,
            'requested_scene_number' => $command->requestedSceneNumber,
            'scene_number' => $scene,
            'channels' => $channels,
            'buses' => $buses,
            'dcas' => $dcas,
            'matrices' => $matrices,
            'fx' => $fx,
            'routing' => $routing,
        ];

        $rawSnapshot = [
            'transport' => 'fake_fixture',
            'host' => $command->host,
            'port' => $command->port,
            'device_key' => $command->device->device_key,
            'requested_scene_number' => $command->requestedSceneNumber,
            'osc_responses' => $this->buildRawOscResponses($command, $channels, $buses, $dcas, $routing),
        ];

        return new X32ConsoleLearnResult(
            success: true,
            summary: $summary,
            rawSnapshot: $rawSnapshot,
            warnings: [
                'Fixture transport in use — live OSC query reads are not performed.',
                sprintf('Scene %s fixture profile applied (fader/mute levels vary by scene number).', $scene),
                'Matrix and FX slot values are representative placeholders.',
                ...($routing['warnings'] ?? []),
            ],
            errors: [],
        );
    }

    private function normalizeSceneNumber(string $sceneNumber): string
    {
        $digits = preg_replace('/\D/', '', $sceneNumber) ?? '';

        if ($digits === '') {
            return '01';
        }

        $value = min(100, max(1, (int) $digits));

        return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
    }

    private function sceneSeed(string $scene): int
    {
        return max(1, (int) ltrim($scene, '0') ?: 1);
    }

    private function fixtureFader(int $index, int $sceneSeed, float $layerBase, float $layerStep): float
    {
        $sceneLift = ($sceneSeed % 12) * 0.045;
        $indexComponent = ($index % 7) * $layerStep;
        $sceneChannelComponent = (($index + $sceneSeed) % 5) * 0.025;

        return round(min(1.0, max(0.0, $layerBase + $sceneLift + $indexComponent + $sceneChannelComponent)), 2);
    }

    private function fixtureMute(int $index, int $sceneSeed, int $modulus): bool
    {
        return ($index + $sceneSeed) % $modulus === 0;
    }

    private function resolveConsoleType(IntegrationDevice $device): ConsoleType
    {
        $model = mb_strtolower((string) ($device->configuration['console_model'] ?? ''));

        return $model === ConsoleType::M32->value ? ConsoleType::M32 : ConsoleType::X32;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChannels(string $scene, int $sceneSeed): array
    {
        $channels = [];

        for ($index = 1; $index <= 32; $index++) {
            $channel = $this->stripWithOscPaths([
                'index' => $index,
                'name' => $this->fixtureChannelName($index, $scene),
                'color' => $this->fixtureChannelColor($index),
                'fader' => $this->fixtureFader($index, $sceneSeed, 0.20, 0.04),
                'mute' => $this->fixtureMute($index, $sceneSeed, 11),
                'controls' => $this->fixtureChannelControls($index, $sceneSeed),
            ], 'channel', $index);

            $channel['sends'] = $index === 1
                ? X32MonitorSendMatrixLearnCapture::fixtureChannelOne()
                : X32MonitorSendMatrixLearnCapture::fixtureChannelDefault($index);

            $channels[] = $channel;
        }

        return $channels;
    }

    private function fixtureChannelName(int $index, string $scene): string
    {
        $names = [
            1 => 'Kick',
            2 => 'Snare',
            3 => 'OH L',
            4 => 'OH R',
            5 => 'Tom 1',
            6 => 'Tom 2',
            7 => 'Bass DI',
            8 => 'Bass Amp',
            9 => 'EGTR L',
            10 => 'EGTR R',
            11 => 'ACGTR',
            12 => 'Keys L',
            13 => 'Keys R',
            14 => 'Vox Lead',
            15 => 'Vox BGV 1',
            16 => 'Vox BGV 2',
            17 => 'Playback L',
            18 => 'Playback R',
            19 => 'Click',
            20 => 'Cajon',
        ];

        return $names[$index] ?? sprintf('Ch %02d · Sc %s', $index, $scene);
    }

    private function fixtureChannelColor(int $index): int
    {
        $colors = [
            1 => 1,
            2 => 1,
            3 => 3,
            4 => 3,
            5 => 1,
            6 => 1,
            7 => 2,
            8 => 2,
            9 => 4,
            10 => 4,
            11 => 4,
            12 => 5,
            13 => 5,
            14 => 6,
            15 => 6,
            16 => 6,
            17 => 7,
            18 => 7,
            19 => 3,
            20 => 1,
        ];

        return $colors[$index] ?? (($index % 7) + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureChannelControls(int $index, int $sceneSeed): array
    {
        return [
            'gate_on' => ($index + $sceneSeed) % 5 === 0,
            'compressor_on' => ($index + $sceneSeed) % 7 === 0,
            'eq_on' => ($index + $sceneSeed) % 3 !== 0,
            'sends_open' => ($index + $sceneSeed) % 9 === 0,
            'pan' => round(min(1.0, max(0.0, 0.5 + ((($index % 5) - 2) * 0.08))), 4),
            'main_lr' => $index <= 28,
            'stereo_link' => $index % 2 === 1 && $index <= 18,
            'gain' => round(min(1.0, max(0.0, 0.42 + (($index % 8) * 0.04))), 4),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBuses(int $sceneSeed): array
    {
        $buses = [];

        for ($index = 1; $index <= 16; $index++) {
            $bus = $this->stripWithOscPaths([
                'index' => $index,
                'name' => sprintf('Bus %02d', $index),
                'color' => ($index % 7) + 1,
                'fader' => $this->fixtureFader($index, $sceneSeed, 0.35, 0.035),
                'mute' => $this->fixtureMute($index, $sceneSeed, 9),
            ], 'bus', $index);

            if ($index === 1) {
                $bus['eq'] = X32BusEqLearnCapture::fixtureBusOne();
            }

            $buses[] = $bus;
        }

        return $buses;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDcas(int $sceneSeed): array
    {
        $dcas = [];

        for ($index = 1; $index <= 8; $index++) {
            $dcas[] = $this->stripWithOscPaths([
                'index' => $index,
                'name' => sprintf('DCA %d', $index),
                'fader' => $this->fixtureFader($index, $sceneSeed, 0.40, 0.03),
                'mute' => false,
            ], 'dca', $index);
        }

        return $dcas;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrices(int $sceneSeed): array
    {
        $matrices = [];

        for ($index = 1; $index <= 6; $index++) {
            $matrices[] = $this->stripWithOscPaths([
                'index' => $index,
                'name' => sprintf('MTRX %d', $index),
                'fader' => $this->fixtureFader($index, $sceneSeed, 0.30, 0.04),
                'mute' => $this->fixtureMute($index, $sceneSeed, 6),
            ], 'matrix', $index);
        }

        return $matrices;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFxSlots(): array
    {
        $fx = [];

        for ($index = 1; $index <= 4; $index++) {
            $fx[] = [
                'slot' => $index,
                'name' => sprintf('FX %d', $index),
                'type' => $index % 2 === 0 ? 'reverb' : 'delay',
                'enabled' => $index !== 4,
            ];
        }

        return $fx;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRouting(X32ConsoleLearnCommand $command, int $sceneSeed): array
    {
        $rawValues = $this->fixtureRoutingRawValues($sceneSeed);

        $routing = $this->routingLearnCapture->captureFromRawValues('fake_fixture', $rawValues);

        $connectivity = $this->sourceConnectivityCapture->captureFromRawValues('fake_fixture', [
            X32SourceConnectivityOscAddressMap::AES50_A => 'Es32',
            X32SourceConnectivityOscAddressMap::AES50_B => 'Cs32',
            X32SourceConnectivityOscAddressMap::AES50_STATE => 0,
            X32SourceConnectivityOscAddressMap::XCARD_TYPE => 2,
        ]);

        return array_merge($routing, [
            'source_connectivity' => $connectivity['normalized'],
        ]);
    }

    /**
     * Representative ESB-style routing fixture — varies slightly by scene seed.
     *
     * @return array<string, int>
     */
    private function fixtureRoutingRawValues(int $sceneSeed): array
    {
        $cardOutIndex = min(35, 16 + ($sceneSeed % 4));

        $values = [
            X32RoutingOscAddressMap::ROUTSWITCH => 0,
            '/config/routing/IN/1-8' => 4,
            '/config/routing/IN/9-16' => 5,
            '/config/routing/IN/17-24' => 10,
            '/config/routing/IN/25-32' => 16,
            '/config/routing/CARD/1-8' => $cardOutIndex,
            '/config/routing/CARD/9-16' => min(35, 17 + ($sceneSeed % 3)),
            '/config/routing/CARD/17-24' => 0,
            '/config/routing/CARD/25-32' => 0,
            '/config/routing/OUT/1-4' => 0,
            '/config/routing/OUT/5-8' => 0,
            '/config/routing/OUT/9-12' => 0,
            '/config/routing/OUT/13-16' => 0,
        ];

        foreach (X32RoutingOscAddressMap::outputMainSrcPaths() as $path) {
            $values[$path] = 0;
        }

        if ($sceneSeed % 5 !== 0) {
            $values[X32RoutingOscAddressMap::outputMainSrcPath(3)] = 1;
            $values[X32RoutingOscAddressMap::outputMainSrcPath(4)] = 2;
        }

        return $values;
    }

    /**
     * @param  array<int, array<string, mixed>>  $channels
     * @param  array<int, array<string, mixed>>  $buses
     * @param  array<int, array<string, mixed>>  $dcas
     * @param  array<string, mixed>  $routing
     * @return array<int, array<string, mixed>>
     */
    private function buildRawOscResponses(
        X32ConsoleLearnCommand $command,
        array $channels,
        array $buses,
        array $dcas,
        array $routing,
    ): array {
        $responses = [
            [
                'path' => '/-action/goscene',
                'args' => ['scene' => $this->normalizeSceneNumber($command->requestedSceneNumber)],
                'direction' => 'outbound_reference_only',
            ],
        ];

        foreach ($channels as $channel) {
            $responses[] = [
                'path' => sprintf('/ch/%02d/mix/fader', $channel['index']),
                'value' => $channel['fader'],
            ];
            $responses[] = [
                'path' => sprintf('/ch/%02d/config/name', $channel['index']),
                'value' => $channel['name'],
            ];
            $responses[] = [
                'path' => sprintf('/ch/%02d/config/color', $channel['index']),
                'value' => $channel['color'] ?? 0,
            ];
            $responses[] = [
                'path' => sprintf('/ch/%02d/mix/on', $channel['index']),
                'value' => $channel['mute'] ? 0 : 1,
            ];

            $controls = is_array($channel['controls'] ?? null) ? $channel['controls'] : [];
            $index = (int) $channel['index'];

            if (array_key_exists('gate_on', $controls)) {
                $responses[] = ['path' => X32OscAddressMap::channelGateOn($index), 'value' => $controls['gate_on'] ? 1 : 0];
            }
            if (array_key_exists('compressor_on', $controls)) {
                $responses[] = ['path' => X32OscAddressMap::channelDynOn($index), 'value' => $controls['compressor_on'] ? 1 : 0];
            }
            if (array_key_exists('eq_on', $controls)) {
                $responses[] = ['path' => X32OscAddressMap::channelEqOn($index), 'value' => $controls['eq_on'] ? 1 : 0];
            }
            if (array_key_exists('pan', $controls)) {
                $responses[] = ['path' => X32OscAddressMap::channelPan($index), 'value' => $controls['pan']];
            }
            if (array_key_exists('main_lr', $controls)) {
                $responses[] = ['path' => X32OscAddressMap::channelLr($index), 'value' => $controls['main_lr'] ? 1 : 0];
            }

            if (is_array($channel['sends'] ?? null)) {
                foreach (X32MonitorSendMatrixLearnCapture::oscSeedsFromCapture($channel['sends']) as $seed) {
                    $responses[] = $seed;
                }
            }
        }

        foreach ($buses as $bus) {
            $responses[] = [
                'path' => sprintf('/bus/%02d/mix/fader', $bus['index']),
                'value' => $bus['fader'],
            ];

            if (is_array($bus['eq'] ?? null)) {
                foreach (X32BusEqLearnCapture::oscSeedsFromCapture($bus['eq']) as $seed) {
                    $responses[] = $seed;
                }
            }
        }

        foreach ($dcas as $dca) {
            $responses[] = [
                'path' => sprintf('/dca/%d/fader', $dca['index']),
                'value' => $dca['fader'],
            ];
        }

        foreach ($this->flattenRoutingRawOsc($routing) as $entry) {
            $responses[] = $entry;
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<array{path: string, value: int}>
     */
    private function flattenRoutingRawOsc(array $routing): array
    {
        $responses = [];
        $rawOsc = is_array($routing['raw_osc'] ?? null) ? $routing['raw_osc'] : [];

        if (is_array($rawOsc['routswitch'] ?? null)) {
            $responses[] = [
                'path' => (string) $rawOsc['routswitch']['path'],
                'value' => (int) $rawOsc['routswitch']['value'],
            ];
        }

        foreach (['input_banks', 'card', 'out_1_16', 'main_output_patch'] as $group) {
            foreach ($rawOsc[$group] ?? [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $responses[] = [
                    'path' => (string) $entry['path'],
                    'value' => (int) $entry['value'],
                ];
            }
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $strip
     * @return array<string, mixed>
     */
    private function stripWithOscPaths(array $strip, string $layer, int $index): array
    {
        return match ($layer) {
            'channel' => $strip + [
                'osc_fader' => X32OscAddressMap::channelFader($index),
                'osc_on' => X32OscAddressMap::channelOn($index),
            ],
            'bus' => $strip + [
                'osc_fader' => X32OscAddressMap::busFader($index),
                'osc_on' => X32OscAddressMap::busOn($index),
            ],
            'dca' => $strip + [
                'osc_fader' => X32OscAddressMap::dcaFader($index),
                'osc_on' => X32OscAddressMap::dcaOn($index),
            ],
            'matrix' => $strip + [
                'osc_fader' => X32OscAddressMap::matrixFader($index),
                'osc_on' => X32OscAddressMap::matrixOn($index),
            ],
            default => $strip,
        };
    }
}
