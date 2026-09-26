<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    /**
     * Stripe general-API zero-decimal currencies. The amount is the major unit.
     *
     * @var list<string>
     */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF',
        'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * Stripe still expects a two-decimal minor amount, and the fraction must be zero.
     *
     * @var list<string>
     */
    private const ZERO_FRACTION_TWO_DECIMAL = [
        'ISK', 'UGX',
    ];

    /**
     * Stripe three-decimal currencies. The last minor digit must be zero.
     *
     * @var list<string>
     */
    private const THREE_DECIMAL = [
        'BHD', 'JOD', 'KWD', 'OMR', 'TND',
    ];

    public static function exponent(string $currency): int
    {
        $currency = strtoupper(trim($currency));

        if (in_array($currency, self::ZERO_DECIMAL, true)) {
            return 0;
        }

        if (in_array($currency, self::THREE_DECIMAL, true)) {
            return 3;
        }

        return 2;
    }

    public static function minorUnits(string $amount, string $currency): int
    {
        $currency = strtoupper(trim($currency));

        if (! Iso4217::isValid($currency)) {
            throw new InvalidArgumentException('Enter an ISO 4217 currency code.');
        }

        $amount = trim($amount);

        if (! preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Enter an amount using digits and an optional decimal point.');
        }

        $exponent = self::exponent($currency);
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        if ($exponent === 0 && $fraction !== '') {
            throw new InvalidArgumentException($currency.' does not use a fractional amount.');
        }

        if (strlen($fraction) > $exponent) {
            throw new InvalidArgumentException($currency.' supports at most '.$exponent.' decimal places.');
        }

        $fraction = str_pad($fraction, $exponent, '0');

        if (in_array($currency, self::ZERO_FRACTION_TWO_DECIMAL, true) && (int) $fraction !== 0) {
            throw new InvalidArgumentException($currency.' does not allow a fractional amount.');
        }

        if (in_array($currency, self::THREE_DECIMAL, true) && $fraction !== '' && ! str_ends_with($fraction, '0')) {
            throw new InvalidArgumentException($currency.' amounts must use two decimal places.');
        }

        $factor = 10 ** $exponent;

        return ((int) $whole * $factor) + (int) ($fraction === '' ? '0' : $fraction);
    }

    public static function toDecimal(int $minor, string $currency): string
    {
        $exponent = self::exponent($currency);

        if ($exponent === 0) {
            return (string) $minor;
        }

        $negative = $minor < 0;
        $minor = abs($minor);
        $factor = 10 ** $exponent;
        $whole = intdiv($minor, $factor);
        $fraction = str_pad((string) ($minor % $factor), $exponent, '0', STR_PAD_LEFT);
        $formatted = $whole.'.'.$fraction;

        return $negative ? '-'.$formatted : $formatted;
    }
}
