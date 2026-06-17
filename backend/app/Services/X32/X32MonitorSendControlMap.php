<?php

namespace App\Services\X32;

/**
 * OSC control map for channel-to-monitor-bus send level and on.
 *
 * @see docs/x32/DECISION_LOG.md X32-DEC-004
 */
class X32MonitorSendControlMap
{
    public const PARAMETER_LEVEL = 'level';

    public const PARAMETER_MUTE = 'mute';

    public const CHANNEL_MIN = 1;

    public const CHANNEL_MAX = 32;

    public const BUS_MIN = 1;

    public const BUS_MAX = 16;

    /** @return list<string> */
    public static function allowedParameters(): array
    {
        return [self::PARAMETER_LEVEL, self::PARAMETER_MUTE];
    }

    public static function clampChannel(int $channel): int
    {
        return min(self::CHANNEL_MAX, max(self::CHANNEL_MIN, $channel));
    }

    public static function clampBus(int $bus): int
    {
        return min(self::BUS_MAX, max(self::BUS_MIN, $bus));
    }

    public static function oscPath(int $channel, int $bus, string $parameter): ?string
    {
        $channel = self::clampChannel($channel);
        $bus = self::clampBus($bus);

        return match ($parameter) {
            self::PARAMETER_LEVEL => X32OscAddressMap::channelBusSendLevel($channel, $bus),
            self::PARAMETER_MUTE => X32OscAddressMap::channelBusSendOn($channel, $bus),
            default => null,
        };
    }

    public static function levelLinearFromRequest(mixed $value): float
    {
        return X32FaderScale::quantizeLinear(max(0.0, min(1.0, (float) $value)));
    }

    public static function muteToSendOn(bool $muted): int
    {
        return $muted ? 0 : 1;
    }

    public static function sendOnToMuted(int $sendOn): bool
    {
        return $sendOn !== 1;
    }

    public static function levelDisplayFromLinear(float $linear): string
    {
        return X32FaderScale::formatDb(X32FaderScale::linearToDb($linear));
    }

    public static function levelsMatch(float $requested, float $confirmed): bool
    {
        return abs(
            X32FaderScale::quantizeLinear($requested) - X32FaderScale::quantizeLinear($confirmed),
        ) < 0.002;
    }
}
