<?php

namespace App\Services\X32;

/**
 * Read-only capture of monitor bus master EQ from X32 OSC query results.
 *
 * Queries `/bus/{NN}/eq/on` and six bands of type/f/g/q per bus.
 */
class X32BusEqLearnCapture
{
    private const BAND_COUNT = 6;

    /**
     * @param  callable(string): float  $queryFloat
     * @param  callable(string): int  $queryInt
     * @return array<string, mixed>
     */
    public function capture(int $busIndex, callable $queryFloat, callable $queryInt): array
    {
        $eqOnPath = X32OscAddressMap::busEqOn($busIndex);
        $eqOn = $queryInt($eqOnPath);

        $bands = [];

        for ($band = 1; $band <= self::BAND_COUNT; $band++) {
            $typePath = X32OscAddressMap::busEqBandType($busIndex, $band);
            $frequencyPath = X32OscAddressMap::busEqBandFrequency($busIndex, $band);
            $gainPath = X32OscAddressMap::busEqBandGain($busIndex, $band);
            $qPath = X32OscAddressMap::busEqBandQ($busIndex, $band);

            $type = $queryInt($typePath);
            $frequencyNormalized = $queryFloat($frequencyPath);
            $gainNormalized = $queryFloat($gainPath);
            $qNormalized = $queryFloat($qPath);

            $bands[] = [
                'number' => $band,
                'type' => $type,
                'f_normalized' => round($frequencyNormalized, 6),
                'f_hz' => X32BusEqOscDecoder::decodeFrequency($frequencyNormalized),
                'g_normalized' => round($gainNormalized, 6),
                'g_db' => X32BusEqOscDecoder::decodeGainDb($gainNormalized),
                'q_normalized' => round($qNormalized, 6),
                'q' => X32BusEqOscDecoder::decodeQ($qNormalized),
                'osc_paths' => [
                    'type' => $typePath,
                    'frequency' => $frequencyPath,
                    'gain' => $gainPath,
                    'q' => $qPath,
                ],
            ];
        }

        return [
            'captured' => true,
            'on' => $eqOn,
            'bands' => $bands,
            'osc_paths' => [
                'on' => $eqOnPath,
            ],
        ];
    }

    /**
     * Representative bus 01 EQ fixture derived from config.bak bus EQ dump.
     *
     * @return array<string, mixed>
     */
    public static function fixtureBusOne(): array
    {
        $bands = [
            ['number' => 1, 'type' => 1, 'f_hz' => 79.6, 'g_db' => 0.0, 'q' => 2.0],
            ['number' => 2, 'type' => 2, 'f_hz' => 158.9, 'g_db' => 0.0, 'q' => 2.0],
            ['number' => 3, 'type' => 2, 'f_hz' => 498.6, 'g_db' => 0.0, 'q' => 2.0],
            ['number' => 4, 'type' => 2, 'f_hz' => 1970.0, 'g_db' => 0.0, 'q' => 2.0],
            ['number' => 5, 'type' => 2, 'f_hz' => 5020.0, 'g_db' => 0.0, 'q' => 2.0],
            ['number' => 6, 'type' => 0, 'f_hz' => 10020.0, 'g_db' => 0.0, 'q' => 2.0],
        ];

        foreach ($bands as &$band) {
            $band['f_normalized'] = round(X32BusEqOscDecoder::encodeFrequency($band['f_hz']), 6);
            $band['g_normalized'] = round(X32BusEqOscDecoder::encodeGainDb($band['g_db']), 6);
            $band['q_normalized'] = round(X32BusEqOscDecoder::encodeQ($band['q']), 6);
            $band['q'] = X32BusEqOscDecoder::decodeQ($band['q_normalized']);
            $band['osc_paths'] = [
                'type' => X32OscAddressMap::busEqBandType(1, $band['number']),
                'frequency' => X32OscAddressMap::busEqBandFrequency(1, $band['number']),
                'gain' => X32OscAddressMap::busEqBandGain(1, $band['number']),
                'q' => X32OscAddressMap::busEqBandQ(1, $band['number']),
            ];
        }
        unset($band);

        return [
            'captured' => true,
            'on' => 0,
            'bands' => $bands,
            'osc_paths' => [
                'on' => X32OscAddressMap::busEqOn(1),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $eq
     * @return list<array{path: string, value: float|int}>
     */
    public static function oscSeedsFromCapture(array $eq): array
    {
        $seeds = [
            ['path' => (string) ($eq['osc_paths']['on'] ?? X32OscAddressMap::busEqOn(1)), 'value' => (int) ($eq['on'] ?? 0)],
        ];

        foreach (is_array($eq['bands'] ?? null) ? $eq['bands'] : [] as $band) {
            if (! is_array($band)) {
                continue;
            }

            $number = (int) ($band['number'] ?? 0);

            if ($number < 1) {
                continue;
            }

            $paths = is_array($band['osc_paths'] ?? null) ? $band['osc_paths'] : [];

            if (isset($paths['type'])) {
                $seeds[] = ['path' => (string) $paths['type'], 'value' => (int) ($band['type'] ?? 0)];
            }
            if (isset($paths['frequency'])) {
                $seeds[] = ['path' => (string) $paths['frequency'], 'value' => (float) ($band['f_normalized'] ?? 0.0)];
            }
            if (isset($paths['gain'])) {
                $seeds[] = ['path' => (string) $paths['gain'], 'value' => (float) ($band['g_normalized'] ?? 0.5)];
            }
            if (isset($paths['q'])) {
                $seeds[] = ['path' => (string) $paths['q'], 'value' => (float) ($band['q_normalized'] ?? 0.5)];
            }
        }

        return $seeds;
    }
}
