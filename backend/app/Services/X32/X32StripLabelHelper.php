<?php

namespace App\Services\X32;

/**
 * Formats channel strip labels for the console workspace UI.
 */
class X32StripLabelHelper
{
    public static function displayName(string $name, int $index, string $labelPrefix = 'CH'): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        if (preg_match('/^CH \d{2} Scene \d+$/i', $name)) {
            return null;
        }

        $normalizedPrefix = strtoupper($labelPrefix);
        if ($name === sprintf('%s %02d', $normalizedPrefix, $index)) {
            return null;
        }

        if ($name === sprintf('CH %02d', $index)) {
            return null;
        }

        return $name;
    }
}
