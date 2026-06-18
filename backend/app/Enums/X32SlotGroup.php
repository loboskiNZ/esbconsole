<?php

namespace App\Enums;

enum X32SlotGroup: string
{
    case Fx1To4 = 'fx1_4';
    case Fx5To8 = 'fx5_8';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Fx1To4 => 'FX1–FX4',
            self::Fx5To8 => 'FX5–FX8',
            self::Any => 'Any slot group',
        };
    }

    /**
     * @return list<int>
     */
    public function allowedSlotNumbers(): array
    {
        return match ($this) {
            self::Fx1To4 => range(1, 4),
            self::Fx5To8 => range(5, 8),
            self::Any => range(1, 8),
        };
    }

    public function allowedSlotsHelper(): string
    {
        return match ($this) {
            self::Fx1To4 => 'FX1–FX4',
            self::Fx5To8 => 'FX5–FX8',
            self::Any => 'FX1–FX8',
        };
    }

    public function slotLabel(int $slotNumber): string
    {
        return 'FX'.$slotNumber;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
