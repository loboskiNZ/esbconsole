<?php

namespace App\Enums;

enum EffectReturnDestination: string
{
    case NotConfigured = 'not_configured';
    case MainLr = 'main_lr';
    case MonitorOnly = 'monitor_only';
    case FxReturn = 'fx_return';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::NotConfigured => 'Not configured',
            self::MainLr => 'Main LR',
            self::MonitorOnly => 'Monitor Only',
            self::FxReturn => 'FX Return',
            self::Custom => 'Custom',
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
