<?php

namespace App\Enums;

enum EffectTempoBehavior: string
{
    case TempoAware = 'tempo_aware';
    case MusicalTimeAware = 'musical_time_aware';
    case TempoNeutral = 'tempo_neutral';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
