<?php

namespace App\Services\X32;

/**
 * OSC stat paths for live source connectivity (not routing tables).
 *
 * Unofficial X32/M32 OSC — see Patrick Maillot protocol doc.
 * Do not confuse /-stat/usbmounted (USB stick mount) with expansion-card audio I/O.
 */
final class X32SourceConnectivityOscAddressMap
{
    public const AES50_A = '/-stat/aes50/A';

    public const AES50_B = '/-stat/aes50/B';

    public const AES50_STATE = '/-stat/aes50/state';

    /** Expansion card type — 0 = none, 2 = X-USB, etc. */
    public const XCARD_TYPE = '/-stat/xcardtype';

    /** @return list<string> */
    public static function statPaths(): array
    {
        return [
            self::AES50_A,
            self::AES50_B,
            self::AES50_STATE,
            self::XCARD_TYPE,
        ];
    }
}
