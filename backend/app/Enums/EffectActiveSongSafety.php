<?php

namespace App\Enums;

enum EffectActiveSongSafety: string
{
    case SafeDuringSong = 'safe_during_song';
    case BetweenSongOnly = 'between_song_only';
    case NotRecommendedLive = 'not_recommended_live';
    case Unknown = 'unknown';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
