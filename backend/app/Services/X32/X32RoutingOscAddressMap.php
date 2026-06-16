<?php

namespace App\Services\X32;

/**
 * Canonical X32/M32 routing OSC paths for read-only routing learn (PH042.03).
 *
 * @see docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md
 */
class X32RoutingOscAddressMap
{
    public const ROUTSWITCH = '/config/routing/routswitch';

    /** @var list<string> */
    public const INPUT_BANK_PATHS = [
        '/config/routing/IN/1-8',
        '/config/routing/IN/9-16',
        '/config/routing/IN/17-24',
        '/config/routing/IN/25-32',
    ];

    /** @var list<string> */
    public const CARD_ROUTING_PATHS = [
        '/config/routing/CARD/1-8',
        '/config/routing/CARD/9-16',
        '/config/routing/CARD/17-24',
        '/config/routing/CARD/25-32',
    ];

    /** @var list<string> */
    public const OUT_BANK_PATHS = [
        '/config/routing/OUT/1-4',
        '/config/routing/OUT/5-8',
        '/config/routing/OUT/9-12',
        '/config/routing/OUT/13-16',
    ];

    /**
     * Physical output source assignment — required to learn Main L/R without assuming Out 15–16.
     *
     * @see docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md §3.15
     *
     * @return list<string>
     */
    public static function outputMainSrcPaths(): array
    {
        $paths = [];

        for ($number = 1; $number <= 16; $number++) {
            $paths[] = self::outputMainSrcPath($number);
        }

        return $paths;
    }

    public static function outputMainSrcPath(int $number): string
    {
        return sprintf('/outputs/main/%02d/src', min(16, max(1, $number)));
    }

    public static function outputMainNumber(string $path): int
    {
        if (preg_match('#^/outputs/main/(\d{2})/src$#', $path, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return array{bank: string, channels: string, start: int, end: int}
     */
    public static function inputBankMeta(string $path): array
    {
        return match ($path) {
            '/config/routing/IN/1-8' => ['bank' => '1-8', 'channels' => 'CH 01–08', 'start' => 1, 'end' => 8],
            '/config/routing/IN/9-16' => ['bank' => '9-16', 'channels' => 'CH 09–16', 'start' => 9, 'end' => 16],
            '/config/routing/IN/17-24' => ['bank' => '17-24', 'channels' => 'CH 17–24', 'start' => 17, 'end' => 24],
            '/config/routing/IN/25-32' => ['bank' => '25-32', 'channels' => 'CH 25–32', 'start' => 25, 'end' => 32],
            default => ['bank' => 'unknown', 'channels' => '—', 'start' => 0, 'end' => 0],
        };
    }

    /**
     * @return array{block: string, outputs: string, start: int, end: int}
     */
    public static function outBankMeta(string $path): array
    {
        return match ($path) {
            '/config/routing/OUT/1-4' => ['block' => '1-4', 'outputs' => 'Out 1–4', 'start' => 1, 'end' => 4],
            '/config/routing/OUT/5-8' => ['block' => '5-8', 'outputs' => 'Out 5–8', 'start' => 5, 'end' => 8],
            '/config/routing/OUT/9-12' => ['block' => '9-12', 'outputs' => 'Out 9–12', 'start' => 9, 'end' => 12],
            '/config/routing/OUT/13-16' => ['block' => '13-16', 'outputs' => 'Out 13–16', 'start' => 13, 'end' => 16],
            default => ['block' => 'unknown', 'outputs' => '—', 'start' => 0, 'end' => 0],
        };
    }

    /**
     * @return array{block: string, card_range: string, start: int, end: int}
     */
    public static function cardBlockMeta(string $path): array
    {
        return match ($path) {
            '/config/routing/CARD/1-8' => ['block' => '1-8', 'card_range' => 'Card 1–8', 'start' => 1, 'end' => 8],
            '/config/routing/CARD/9-16' => ['block' => '9-16', 'card_range' => 'Card 9–16', 'start' => 9, 'end' => 16],
            '/config/routing/CARD/17-24' => ['block' => '17-24', 'card_range' => 'Card 17–24', 'start' => 17, 'end' => 24],
            '/config/routing/CARD/25-32' => ['block' => '25-32', 'card_range' => 'Card 25–32', 'start' => 25, 'end' => 32],
            default => ['block' => 'unknown', 'card_range' => '—', 'start' => 0, 'end' => 0],
        };
    }

    public static function usesOutBankFourWideEnum(string $path): bool
    {
        return in_array($path, ['/config/routing/OUT/1-4', '/config/routing/OUT/9-12'], true);
    }
}
