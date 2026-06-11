<?php

namespace App\Enums;

enum BandRole: string
{
    case Director = 'director';
    case Musician = 'musician';
    case SoundEngineer = 'sound_engineer';
    case LightingEngineer = 'lighting_engineer';
    case TravelManager = 'travel_manager';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Director => 'Director',
            self::Musician => 'Musician',
            self::SoundEngineer => 'Sound Engineer',
            self::LightingEngineer => 'Lighting Engineer',
            self::TravelManager => 'Travel Manager',
            self::Agent => 'Agent',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
