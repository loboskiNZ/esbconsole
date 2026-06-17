<?php

namespace App\Services\X32;

/**
 * Read-only capture of channel-to-monitor-bus send matrix from X32 OSC queries.
 */
class X32MonitorSendMatrixLearnCapture
{
    private const CHANNEL_COUNT = 32;

    private const BUS_COUNT = 16;

    /**
     * @param  callable(string): float  $queryFloat
     * @param  callable(string): int  $queryInt
     * @return array<string, mixed>
     */
    public function captureForChannel(int $channelIndex, callable $queryFloat, callable $queryInt): array
    {
        $buses = [];

        for ($bus = 1; $bus <= self::BUS_COUNT; $bus++) {
            $buses[$bus] = $this->captureSend($channelIndex, $bus, $queryFloat, $queryInt);
        }

        return [
            'captured' => true,
            'buses' => $buses,
        ];
    }

    /**
     * @param  callable(string): float  $queryFloat
     * @param  callable(string): int  $queryInt
     * @return array<string, mixed>
     */
    public function captureSend(int $channelIndex, int $busIndex, callable $queryFloat, callable $queryInt): array
    {
        $onPath = X32OscAddressMap::channelBusSendOn($channelIndex, $busIndex);
        $levelPath = X32OscAddressMap::channelBusSendLevel($channelIndex, $busIndex);

        $on = $queryInt($onPath);
        $level = $queryFloat($levelPath);

        $send = [
            'bus' => $busIndex,
            'on' => $on,
            'level' => round($level, 6),
            'level_db' => round(X32FaderScale::linearToDb($level), 2),
            'osc_paths' => [
                'on' => $onPath,
                'level' => $levelPath,
            ],
        ];

        if (X32ChannelBusSendOscDecoder::busSupportsSendPan($busIndex)) {
            $panPath = X32OscAddressMap::channelBusSendPan($channelIndex, $busIndex);
            $panNormalized = $queryFloat($panPath);
            $send['pan_normalized'] = round($panNormalized, 6);
            $send['pan'] = X32ChannelBusSendOscDecoder::decodePan($panNormalized);
            $send['osc_paths']['pan'] = $panPath;
        }

        if (X32ChannelBusSendOscDecoder::busSupportsSendType($busIndex)) {
            $typePath = X32OscAddressMap::channelBusSendType($channelIndex, $busIndex);
            $type = $queryInt($typePath);
            $send['type'] = $type;
            $send['tap'] = X32ChannelBusSendOscDecoder::typeToTap($type);
            $send['osc_paths']['type'] = $typePath;
        }

        if (X32ChannelBusSendOscDecoder::busSupportsSendPanFollow($busIndex)) {
            $panFollowPath = X32OscAddressMap::channelBusSendPanFollow($channelIndex, $busIndex);
            $send['pan_follow'] = $queryInt($panFollowPath);
            $send['osc_paths']['pan_follow'] = $panFollowPath;
        }

        return $send;
    }

    /**
     * Representative fixture for channel 1 sends to bus 1 (Kick → Ed IEM).
     *
     * @return array<string, mixed>
     */
    public static function fixtureChannelOne(): array
    {
        $buses = [];

        for ($bus = 1; $bus <= self::BUS_COUNT; $bus++) {
            if ($bus === 1) {
                $level = 0.75;
                $buses[$bus] = [
                    'bus' => 1,
                    'on' => 1,
                    'level' => $level,
                    'level_db' => round(X32FaderScale::linearToDb($level), 2),
                    'pan_normalized' => X32ChannelBusSendOscDecoder::encodePan(0.0),
                    'pan' => 0.0,
                    'type' => 4,
                    'tap' => 'post_fader',
                    'osc_paths' => [
                        'on' => X32OscAddressMap::channelBusSendOn(1, 1),
                        'level' => X32OscAddressMap::channelBusSendLevel(1, 1),
                        'pan' => X32OscAddressMap::channelBusSendPan(1, 1),
                        'type' => X32OscAddressMap::channelBusSendType(1, 1),
                    ],
                ];

                continue;
            }

            if ($bus === 2) {
                $level = 0.5;
                $buses[$bus] = [
                    'bus' => 2,
                    'on' => 1,
                    'level' => $level,
                    'level_db' => round(X32FaderScale::linearToDb($level), 2),
                    'osc_paths' => [
                        'on' => X32OscAddressMap::channelBusSendOn(1, 2),
                        'level' => X32OscAddressMap::channelBusSendLevel(1, 2),
                    ],
                ];

                continue;
            }

            $buses[$bus] = [
                'bus' => $bus,
                'on' => 0,
                'level' => 0.0,
                'level_db' => round(X32FaderScale::linearToDb(0.0), 2),
                'osc_paths' => [
                    'on' => X32OscAddressMap::channelBusSendOn(1, $bus),
                    'level' => X32OscAddressMap::channelBusSendLevel(1, $bus),
                ],
            ];
        }

        return [
            'captured' => true,
            'buses' => $buses,
        ];
    }

    /**
     * Minimal off-state sends for channels 2–32 (bus 1 only has non-zero kick on bus 1).
     *
     * @return array<string, mixed>
     */
    public static function fixtureChannelDefault(int $channelIndex): array
    {
        $buses = [];

        for ($bus = 1; $bus <= self::BUS_COUNT; $bus++) {
            $on = ($channelIndex === 14 && $bus === 1) ? 1 : 0;
            $level = ($channelIndex === 14 && $bus === 1) ? 0.625 : 0.0;

            $entry = [
                'bus' => $bus,
                'on' => $on,
                'level' => $level,
                'level_db' => round(X32FaderScale::linearToDb($level), 2),
                'osc_paths' => [
                    'on' => X32OscAddressMap::channelBusSendOn($channelIndex, $bus),
                    'level' => X32OscAddressMap::channelBusSendLevel($channelIndex, $bus),
                ],
            ];

            if ($bus === 1 && $channelIndex === 14) {
                $entry['pan_normalized'] = X32ChannelBusSendOscDecoder::encodePan(-12.0);
                $entry['pan'] = -12.0;
                $entry['type'] = 3;
                $entry['tap'] = 'pre_fader';
                $entry['osc_paths']['pan'] = X32OscAddressMap::channelBusSendPan($channelIndex, 1);
                $entry['osc_paths']['type'] = X32OscAddressMap::channelBusSendType($channelIndex, 1);
            }

            $buses[$bus] = $entry;
        }

        return [
            'captured' => true,
            'buses' => $buses,
        ];
    }

    /**
     * @param  array<string, mixed>  $sends
     * @return list<array{path: string, value: float|int}>
     */
    public static function oscSeedsFromCapture(array $sends): array
    {
        $seeds = [];

        foreach (is_array($sends['buses'] ?? null) ? $sends['buses'] : [] as $bus) {
            if (! is_array($bus)) {
                continue;
            }

            $paths = is_array($bus['osc_paths'] ?? null) ? $bus['osc_paths'] : [];

            if (isset($paths['on'])) {
                $seeds[] = ['path' => (string) $paths['on'], 'value' => (int) ($bus['on'] ?? 0)];
            }
            if (isset($paths['level'])) {
                $seeds[] = ['path' => (string) $paths['level'], 'value' => (float) ($bus['level'] ?? 0.0)];
            }
            if (isset($paths['pan'])) {
                $seeds[] = ['path' => (string) $paths['pan'], 'value' => (float) ($bus['pan_normalized'] ?? 0.5)];
            }
            if (isset($paths['type'])) {
                $seeds[] = ['path' => (string) $paths['type'], 'value' => (int) ($bus['type'] ?? 0)];
            }
            if (isset($paths['pan_follow'])) {
                $seeds[] = ['path' => (string) $paths['pan_follow'], 'value' => (int) ($bus['pan_follow'] ?? 0)];
            }
        }

        return $seeds;
    }
}
