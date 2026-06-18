<?php

namespace App\Enums;

enum EffectPackageType: string
{
    case Permanent = 'permanent';
    case SongSelectable = 'song_selectable';
    case SpecialTreatment = 'special_treatment';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function reservesSlotsGlobally(): bool
    {
        return $this === self::Permanent;
    }

    public function mayReuseSlotsAcrossPackages(): bool
    {
        return $this === self::SongSelectable || $this === self::SpecialTreatment;
    }
}
