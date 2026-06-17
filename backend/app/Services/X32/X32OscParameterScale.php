<?php

namespace App\Services\X32;

/**
 * Decode and encode X32 OSC linf/logf normalized floats.
 *
 * @see docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md — Bus master EQ scaling
 */
class X32OscParameterScale
{
    public static function decodeLogf(float $normalized, float $min, float $max, int $steps): float
    {
        $normalized = max(0.0, min(1.0, $normalized));
        $index = (int) round($normalized * max(1, $steps - 1));
        $index = max(0, min(max(1, $steps - 1), $index));

        if ($steps <= 1) {
            return $min;
        }

        $ratio = $index / ($steps - 1);

        if ($min <= 0.0 || $max <= 0.0) {
            return $min;
        }

        return $min * ($max / $min) ** $ratio;
    }

    public static function decodeLinf(float $normalized, float $min, float $max, float $step): float
    {
        $normalized = max(0.0, min(1.0, $normalized));
        $raw = $min + ($normalized * ($max - $min));

        if ($step <= 0.0) {
            return $raw;
        }

        return round($raw / $step) * $step;
    }

    public static function encodeLogf(float $value, float $min, float $max, int $steps): float
    {
        if ($steps <= 1 || $min <= 0.0 || $max <= 0.0) {
            return 0.0;
        }

        $clamped = max(min($min, $max), min(max($min, $max), $value));
        $ratio = log($clamped / $min, $max / $min);
        $index = $ratio * ($steps - 1);

        return max(0.0, min(1.0, $index / ($steps - 1)));
    }

    public static function encodeLinf(float $value, float $min, float $max, float $step): float
    {
        if ($max === $min) {
            return 0.0;
        }

        $clamped = max($min, min($max, $value));

        if ($step > 0.0) {
            $clamped = round($clamped / $step) * $step;
        }

        return max(0.0, min(1.0, ($clamped - $min) / ($max - $min)));
    }
}
