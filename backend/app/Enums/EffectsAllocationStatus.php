<?php

namespace App\Enums;

enum EffectsAllocationStatus: string
{
    case Ready = 'READY';
    case ReadyWithWarnings = 'READY_WITH_WARNINGS';
    case Blocked = 'BLOCKED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
