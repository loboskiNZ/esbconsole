<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Formats a stored UTC instant in a Performance ticketing timezone.
 * The source instant is copied and is not mutated.
 */
final class PerformanceEventTime
{
    public const DISPLAY_FORMAT = 'j M Y, H:i';

    public static function format(?Carbon $instant, ?string $timezone, string $format = self::DISPLAY_FORMAT): string
    {
        if ($instant === null) {
            return '';
        }

        return $instant->copy()->timezone(self::identifier($timezone))->format($format);
    }

    /**
     * A stored IANA identifier, or UTC when the Performance has no ticketing timezone.
     */
    public static function identifier(?string $timezone): string
    {
        $timezone = is_string($timezone) ? trim($timezone) : '';

        if ($timezone !== '' && self::isIdentifier($timezone)) {
            return $timezone;
        }

        return 'UTC';
    }

    private static function isIdentifier(string $timezone): bool
    {
        static $identifiers = null;

        if ($identifiers === null) {
            $identifiers = array_fill_keys(timezone_identifiers_list(), true);
        }

        return isset($identifiers[$timezone]);
    }
}
