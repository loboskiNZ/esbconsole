<?php

namespace App\Services\X32;

/**
 * OSC control map for monitor bus master fader and on.
 */
class X32MonitorBusMasterControlMap
{
    public const PARAMETER_LEVEL = 'level';

    public const PARAMETER_MUTE = 'mute';

    public const BUS_MIN = 1;

    public const BUS_MAX = 16;

    /** @return list<string> */
    public static function allowedParameters(): array
    {
        return [self::PARAMETER_LEVEL, self::PARAMETER_MUTE];
    }

    public static function clampBus(int $bus): int
    {
        return min(self::BUS_MAX, max(self::BUS_MIN, $bus));
    }

    public static function oscPath(int $bus, string $parameter): ?string
    {
        $bus = self::clampBus($bus);

        return match ($parameter) {
            self::PARAMETER_LEVEL => X32OscAddressMap::busFader($bus),
            self::PARAMETER_MUTE => X32OscAddressMap::busOn($bus),
            default => null,
        };
    }

    public static function levelLinearFromRequest(mixed $value): float
    {
        return X32FaderScale::quantizeLinear(max(0.0, min(1.0, (float) $value)));
    }

    /**
     * Bus on = 1 means bus active (unmuted). UI mute inverts this visually.
     */
    public static function muteToBusOn(bool $muted): int
    {
        return $muted ? 0 : 1;
    }

    public static function busOnToMuted(int $busOn): bool
    {
        return $busOn !== 1;
    }

    public static function levelDisplayFromLinear(float $linear): string
    {
        return X32FaderScale::formatDb(X32FaderScale::linearToDb($linear)).' dB';
    }

    public static function levelsMatch(float $requested, float $confirmed): bool
    {
        return abs(
            X32FaderScale::quantizeLinear($requested) - X32FaderScale::quantizeLinear($confirmed),
        ) < 0.002;
    }
}
