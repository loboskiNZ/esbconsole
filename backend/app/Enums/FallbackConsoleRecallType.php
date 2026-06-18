<?php

namespace App\Enums;

enum FallbackConsoleRecallType: string
{
    case Scene = 'scene';
    case Snippet = 'snippet';
    case Cue = 'cue';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
