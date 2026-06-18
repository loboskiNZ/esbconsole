<?php

namespace App\Enums;

enum EffectImplementationType: string
{
    case FxSlot = 'fx_slot';
    case ChannelProcessing = 'channel_processing';
    case MainProcessing = 'main_processing';
    case Hybrid = 'hybrid';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
