<?php

namespace App\Services\X32;

/**
 * Decode X32 bus master EQ OSC parameters for monitor workspace learning.
 *
 * OSC evidence: Patrick Gilles Maillot X32/M32 OSC Remote Protocol (bus EQ chapter).
 */
class X32BusEqOscDecoder
{
    public const FREQ_MIN_HZ = 20.0;

    public const FREQ_MAX_HZ = 20000.0;

    public const FREQ_STEPS = 201;

    public const GAIN_MIN_DB = -15.0;

    public const GAIN_MAX_DB = 15.0;

    public const GAIN_STEP_DB = 0.25;

    public const Q_MIN = 10.0;

    public const Q_MAX = 0.3;

    public const Q_STEPS = 72;

    /** @var array<int, string> */
    private const TYPE_LABELS = [
        0 => 'LCUT',
        1 => 'LSHV',
        2 => 'PEQ',
        3 => 'VEQ',
        4 => 'HSHV',
        5 => 'HCUT',
        6 => 'BU6',
        7 => 'BU12',
        8 => 'BS12',
        9 => 'LR12',
        10 => 'BU18',
        11 => 'BU24',
        12 => 'BS24',
        13 => 'LR24',
    ];

    public static function decodeFrequency(?float $normalized): ?float
    {
        if ($normalized === null) {
            return null;
        }

        return round(self::decodeLogfFrequency($normalized), 1);
    }

    public static function decodeGainDb(?float $normalized): ?float
    {
        if ($normalized === null) {
            return null;
        }

        return round(self::decodeLinfGain($normalized), 2);
    }

    public static function decodeQ(?float $normalized): ?float
    {
        if ($normalized === null) {
            return null;
        }

        return round(self::decodeLogfQ($normalized), 2);
    }

    public static function decodeLogfFrequency(float $normalized): float
    {
        return X32OscParameterScale::decodeLogf(
            $normalized,
            self::FREQ_MIN_HZ,
            self::FREQ_MAX_HZ,
            self::FREQ_STEPS,
        );
    }

    public static function decodeLinfGain(float $normalized): float
    {
        return X32OscParameterScale::decodeLinf(
            $normalized,
            self::GAIN_MIN_DB,
            self::GAIN_MAX_DB,
            self::GAIN_STEP_DB,
        );
    }

    public static function decodeLogfQ(float $normalized): float
    {
        return X32OscParameterScale::decodeLogf(
            $normalized,
            self::Q_MIN,
            self::Q_MAX,
            self::Q_STEPS,
        );
    }

    public static function encodeFrequency(float $hz): float
    {
        return X32OscParameterScale::encodeLogf(
            $hz,
            self::FREQ_MIN_HZ,
            self::FREQ_MAX_HZ,
            self::FREQ_STEPS,
        );
    }

    public static function encodeGainDb(float $db): float
    {
        return X32OscParameterScale::encodeLinf(
            $db,
            self::GAIN_MIN_DB,
            self::GAIN_MAX_DB,
            self::GAIN_STEP_DB,
        );
    }

    public static function encodeQ(float $q): float
    {
        return X32OscParameterScale::encodeLogf(
            $q,
            self::Q_MIN,
            self::Q_MAX,
            self::Q_STEPS,
        );
    }

    public static function typeToMode(?int $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::TYPE_LABELS[$type] ?? null;
    }

    public static function modeIsSupportedInMonitorCard(?string $mode): bool
    {
        if ($mode === null) {
            return false;
        }

        return in_array($mode, ['LCUT', 'LSHV', 'PEQ', 'VEQ', 'HSHV', 'HCUT'], true);
    }
}
