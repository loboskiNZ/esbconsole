<?php

namespace App\Services\X32;

/**
 * Converts X32/M32 fader OSC linear levels (0.0–1.0) to decibels and back.
 *
 * Piecewise mapping per Behringer X32 OSC protocol (Patrick Gilles Maillot).
 * Unity (0 dB) = 0.75 linear; +10 dB = 1.0; -60 dB = 0.0625.
 */
class X32FaderScale
{
    public const LINEAR_UNITY = 0.75;

    public const LINEAR_MAX = 1.0;

    public const DB_MIN = -90.0;

    public const DB_MAX = 10.0;

    public const DISPLAY_DB_MIN = -60.0;

    public static function linearToDb(float $linear): float
    {
        $linear = max(0.0, min(self::LINEAR_MAX, $linear));

        if ($linear >= 0.5) {
            return ($linear * 40.0) - 30.0;
        }

        if ($linear >= 0.25) {
            return ($linear * 80.0) - 50.0;
        }

        if ($linear >= 0.0625) {
            return ($linear * 160.0) - 70.0;
        }

        return ($linear * 480.0) - 90.0;
    }

    public static function dbToLinear(float $db): float
    {
        if ($db <= -60.0) {
            return max(0.0, ($db + 90.0) / 480.0);
        }

        if ($db < -30.0) {
            return ($db + 70.0) / 160.0;
        }

        if ($db < -10.0) {
            return ($db + 50.0) / 80.0;
        }

        return min(self::LINEAR_MAX, ($db + 30.0) / 40.0);
    }

    public static function formatDb(float $db): string
    {
        if ($db <= -89.5) {
            return '−∞';
        }

        $rounded = round($db, 1);

        if ($rounded > 0.0) {
            return sprintf('+%.1f', $rounded);
        }

        if ($rounded === 0.0) {
            return '0.0';
        }

        return sprintf('%.1f', $rounded);
    }

    public static function unityMarkPercent(): float
    {
        return self::LINEAR_UNITY * 100;
    }

    public static function linearMarkPercent(float $linear): float
    {
        return max(0.0, min(100.0, $linear * 100));
    }

    /**
     * X32 fader scale ticks — positions are OSC linear, labels are dB.
     *
     * @return list<array{db: float, linear: float, label: ?string, major: bool, unity?: bool}>
     */
    public static function scaleMarks(): array
    {
        return [
            ['db' => 10.0, 'linear' => 1.0, 'label' => '+10', 'major' => true],
            ['db' => 0.0, 'linear' => self::LINEAR_UNITY, 'label' => '0', 'major' => true, 'unity' => true],
            ['db' => -10.0, 'linear' => 0.5, 'label' => null, 'major' => false],
            ['db' => -30.0, 'linear' => 0.25, 'label' => null, 'major' => false],
            ['db' => -60.0, 'linear' => 0.0625, 'label' => '−60', 'major' => true],
        ];
    }

    /**
     * Console fader scale ticks — matches virtual console / FADER_SCALE_TICKS.
     *
     * @return list<array{db: float, linear: float, label: string, unity: bool, pct: float}>
     */
    public static function consoleScaleMarks(): array
    {
        $marks = [
            ['db' => 10.0, 'linear' => 1.0, 'label' => '+10', 'unity' => false],
            ['db' => 5.0, 'linear' => 0.875, 'label' => '+5', 'unity' => false],
            ['db' => 0.0, 'linear' => self::LINEAR_UNITY, 'label' => '0', 'unity' => true],
            ['db' => -5.0, 'linear' => 0.625, 'label' => '-5', 'unity' => false],
            ['db' => -10.0, 'linear' => 0.5, 'label' => '-10', 'unity' => false],
            ['db' => -20.0, 'linear' => 0.375, 'label' => '-20', 'unity' => false],
            ['db' => -30.0, 'linear' => 0.25, 'label' => '-30', 'unity' => false],
            ['db' => -40.0, 'linear' => 0.1875, 'label' => '-40', 'unity' => false],
            ['db' => -60.0, 'linear' => 0.0625, 'label' => '-60', 'unity' => false],
            ['db' => -90.0, 'linear' => 0.0, 'label' => '−∞', 'unity' => false],
        ];

        return array_map(static function (array $mark): array {
            $mark['pct'] = self::linearMarkPercent($mark['linear']);

            return $mark;
        }, $marks);
    }

    public static function dbMarkPercent(float $db): float
    {
        return self::linearMarkPercent(self::dbToLinear($db));
    }

    public static function quantizeLinear(float $linear): float
    {
        $linear = max(0.0, min(self::LINEAR_MAX, $linear));

        return (int) round($linear * 1023.5) / 1023.0;
    }
}
