<?php

namespace App\Services\X32;

/**
 * Decode X32 channel-to-bus send OSC parameters.
 *
 * @see docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md — Monitor send matrix
 */
class X32ChannelBusSendOscDecoder
{
    /** @var array<int, string> */
    private const TYPE_LABELS = [
        0 => 'in_lc',
        1 => 'pre_eq',
        2 => 'post_eq',
        3 => 'pre_fader',
        4 => 'post_fader',
        5 => 'grp',
    ];

    public static function decodePan(?float $normalized): ?float
    {
        if ($normalized === null) {
            return null;
        }

        return round(
            X32OscParameterScale::decodeLinf($normalized, -100.0, 100.0, 2.0),
            1,
        );
    }

    public static function encodePan(float $pan): float
    {
        return X32OscParameterScale::encodeLinf($pan, -100.0, 100.0, 2.0);
    }

    public static function typeToTap(?int $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::TYPE_LABELS[$type] ?? null;
    }

    public static function busSupportsSendPan(int $bus): bool
    {
        return $bus >= 1 && $bus <= 16 && $bus % 2 === 1;
    }

    public static function busSupportsSendType(int $bus): bool
    {
        return self::busSupportsSendPan($bus);
    }

    public static function busSupportsSendPanFollow(int $bus): bool
    {
        return $bus >= 3 && $bus <= 15 && $bus % 2 === 1;
    }
}
