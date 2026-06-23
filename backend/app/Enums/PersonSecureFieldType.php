<?php

namespace App\Enums;

enum PersonSecureFieldType: string
{
    case BankAccount = 'bank_account';
    case PassportNumber = 'passport_number';
    case AirNewZealandPoints = 'air_new_zealand_points';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
