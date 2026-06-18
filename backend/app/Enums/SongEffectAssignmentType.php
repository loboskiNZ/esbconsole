<?php

namespace App\Enums;

enum SongEffectAssignmentType: string
{
    case Default = 'default';
    case SongSpecific = 'song_specific';
    case TransitionOnly = 'transition_only';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
