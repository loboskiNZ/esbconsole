<?php

namespace App\Services\X32;

/**
 * X32/M32 channel color indices (OSC /ch/NN/config/color integer 0–15).
 *
 * Indices 8–15 mirror 0–7 on the desk; we map them to the same display colours.
 */
class X32ChannelColorMap
{
    /** @var array<int, array{css: string, label: string, text: string}> */
    private const PALETTE = [
        0 => ['css' => '#3f3f46', 'label' => 'Off', 'text' => '#e4e4e7'],
        1 => ['css' => '#c03030', 'label' => 'Red', 'text' => '#ffffff'],
        2 => ['css' => '#30a030', 'label' => 'Green', 'text' => '#ffffff'],
        3 => ['css' => '#c0c030', 'label' => 'Yellow', 'text' => '#1c1c1e'],
        4 => ['css' => '#3030c0', 'label' => 'Blue', 'text' => '#ffffff'],
        5 => ['css' => '#c030c0', 'label' => 'Magenta', 'text' => '#ffffff'],
        6 => ['css' => '#30c0c0', 'label' => 'Cyan', 'text' => '#1c1c1e'],
        7 => ['css' => '#d4d4d8', 'label' => 'White', 'text' => '#1c1c1e'],
    ];

    public static function normalizeIndex(?int $index): int
    {
        if ($index === null) {
            return 0;
        }

        $index = max(0, min(15, $index));

        return $index >= 8 ? $index - 8 : $index;
    }

    public static function cssColor(?int $index): string
    {
        return self::PALETTE[self::normalizeIndex($index)]['css'];
    }

    public static function label(?int $index): string
    {
        return self::PALETTE[self::normalizeIndex($index)]['label'];
    }

    public static function textColor(?int $index): string
    {
        return self::PALETTE[self::normalizeIndex($index)]['text'];
    }

    /**
     * @return array{css: string, label: string, text: string, index: int}
     */
    public static function resolve(?int $index): array
    {
        $normalized = self::normalizeIndex($index);
        $entry = self::PALETTE[$normalized];

        return [
            'index' => $normalized,
            'css' => $entry['css'],
            'label' => $entry['label'],
            'text' => $entry['text'],
        ];
    }
}
