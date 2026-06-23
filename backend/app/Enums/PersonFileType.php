<?php

namespace App\Enums;

enum PersonFileType: string
{
    case PassportPhoto = 'passport_photo';
    case Visa = 'visa';
    case Contract = 'contract';
    case InvoiceDocument = 'invoice_document';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
