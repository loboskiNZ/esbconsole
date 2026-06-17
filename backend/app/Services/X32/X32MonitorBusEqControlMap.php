<?php

namespace App\Services\X32;

/**
 * OSC control map for monitor bus master EQ live writes.
 */
class X32MonitorBusEqControlMap
{
    public const PARAMETER_ON = 'on';

    public const PARAMETER_TYPE = 'type';

    public const PARAMETER_FREQUENCY = 'f';

    public const PARAMETER_GAIN = 'g';

    public const PARAMETER_Q = 'q';

    public const BAND_MIN = 1;

    public const BAND_MAX = 6;

    public const BUS_MIN = 1;

    public const BUS_MAX = 16;

    /** @return list<string> */
    public static function allowedParameters(): array
    {
        return [
            self::PARAMETER_ON,
            self::PARAMETER_TYPE,
            self::PARAMETER_FREQUENCY,
            self::PARAMETER_GAIN,
            self::PARAMETER_Q,
        ];
    }

    public static function clampBus(int $bus): int
    {
        return min(self::BUS_MAX, max(self::BUS_MIN, $bus));
    }

    public static function clampBand(int $band): int
    {
        return min(self::BAND_MAX, max(self::BAND_MIN, $band));
    }

    public static function oscPath(int $bus, string $parameter, ?int $band = null): ?string
    {
        $bus = self::clampBus($bus);

        return match ($parameter) {
            self::PARAMETER_ON => X32OscAddressMap::busEqOn($bus),
            self::PARAMETER_TYPE => $band !== null
                ? X32OscAddressMap::busEqBandType($bus, self::clampBand($band))
                : null,
            self::PARAMETER_FREQUENCY => $band !== null
                ? X32OscAddressMap::busEqBandFrequency($bus, self::clampBand($band))
                : null,
            self::PARAMETER_GAIN => $band !== null
                ? X32OscAddressMap::busEqBandGain($bus, self::clampBand($band))
                : null,
            self::PARAMETER_Q => $band !== null
                ? X32OscAddressMap::busEqBandQ($bus, self::clampBand($band))
                : null,
            default => null,
        };
    }

    public static function onFromRequest(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    public static function onToEnabled(int $on): bool
    {
        return $on === 1;
    }

    public static function typeFromRequest(mixed $value): int
    {
        if (is_numeric($value)) {
            $type = (int) $value;
        } else {
            $type = X32BusEqOscDecoder::modeToType((string) $value) ?? -1;
        }

        $mode = X32BusEqOscDecoder::typeToMode($type);

        if (! X32BusEqOscDecoder::modeIsSupportedInMonitorCard($mode)) {
            throw new \InvalidArgumentException('Unsupported bus EQ filter type.');
        }

        return $type;
    }

    public static function frequencyHzFromRequest(mixed $value): float
    {
        return min(
            X32BusEqOscDecoder::FREQ_MAX_HZ,
            max(X32BusEqOscDecoder::FREQ_MIN_HZ, (float) $value),
        );
    }

    public static function gainDbFromRequest(mixed $value): float
    {
        $db = (float) $value;

        return round(
            min(X32BusEqOscDecoder::GAIN_MAX_DB, max(X32BusEqOscDecoder::GAIN_MIN_DB, $db)) / X32BusEqOscDecoder::GAIN_STEP_DB,
        ) * X32BusEqOscDecoder::GAIN_STEP_DB;
    }

    public static function qFromRequest(mixed $value): float
    {
        return max(0.1, (float) $value);
    }

    public static function normalizedValuesMatch(float $requested, float $confirmed): bool
    {
        return abs($requested - $confirmed) < 0.0005;
    }

    public static function frequencyDisplay(float $hz): string
    {
        if ($hz >= 1000) {
            $khz = $hz / 1000;
            $whole = (int) floor($khz);
            $fraction = (int) round(($khz - $whole) * 100);

            return sprintf('%dK%02d', $whole, $fraction);
        }

        $formatted = number_format($hz, 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted;
    }

    public static function gainDisplay(float $db): string
    {
        if (abs($db) < 0.05) {
            return '0';
        }

        $prefix = $db > 0 ? '+' : '';

        return $prefix.number_format($db, 1, '.', '');
    }

    public static function qDisplay(float $q): string
    {
        return rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');
    }
}
