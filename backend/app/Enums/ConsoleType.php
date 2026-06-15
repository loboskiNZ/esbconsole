<?php

namespace App\Enums;

enum ConsoleType: string
{
    case X32 = 'x32';
    case M32 = 'm32';

    public function label(): string
    {
        return match ($this) {
            self::X32 => 'X32',
            self::M32 => 'M32',
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
