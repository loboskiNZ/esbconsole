<?php

namespace App\Services\X32;

/**
 * Read-only capture of console identity globals from live OSC.
 */
class X32ConfigurationIdentityCapture
{
    /** @var array<int, string> */
    private const SAMPLE_RATES = [
        0 => '48K',
        1 => '44.1K',
    ];

    /** @var array<int, string> */
    private const CLOCK_SOURCES = [
        0 => 'INT',
        1 => 'AES50A',
        2 => 'AES50B',
        3 => 'EXP',
    ];

    /**
     * @param  callable(string): int  $queryInt
     * @return array<string, mixed>
     */
    public function capture(callable $queryInt): array
    {
        $sampleRateIndex = $queryInt(X32OscAddressMap::clockRate());
        $clockSourceIndex = $queryInt(X32OscAddressMap::clockSource());

        return [
            'sample_rate' => [
                'value' => self::SAMPLE_RATES[$sampleRateIndex] ?? null,
                'raw_index' => $sampleRateIndex,
                'state' => array_key_exists($sampleRateIndex, self::SAMPLE_RATES) ? 'learned' : 'not_learned',
                'reason' => array_key_exists($sampleRateIndex, self::SAMPLE_RATES) ? null : 'unknown_enum_index',
            ],
            'clock_source' => [
                'value' => self::CLOCK_SOURCES[$clockSourceIndex] ?? null,
                'raw_index' => $clockSourceIndex,
                'state' => array_key_exists($clockSourceIndex, self::CLOCK_SOURCES) ? 'learned' : 'not_learned',
                'reason' => array_key_exists($clockSourceIndex, self::CLOCK_SOURCES) ? null : 'unknown_enum_index',
            ],
            'firmware' => [
                'value' => null,
                'state' => 'not_learned',
                'reason' => 'info_query_not_implemented',
            ],
            'desk_name' => [
                'value' => null,
                'state' => 'not_learned',
                'reason' => 'xinfo_query_not_implemented',
            ],
        ];
    }
}
