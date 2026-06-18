<?php

namespace App\Enums;

enum EffectRoutingMode: string
{
    case NotConfigured = 'not_configured';
    case SendReturn = 'send_return';
    case Insert = 'insert';
    case MainProcessing = 'main_processing';

    public function label(): string
    {
        return match ($this) {
            self::NotConfigured => 'Not configured',
            self::SendReturn => 'Send/Return',
            self::Insert => 'Insert',
            self::MainProcessing => 'Main Processing',
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
