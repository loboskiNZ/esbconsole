<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_two_decimal_currency_stores_minor_units(): void
    {
        $this->assertSame(1950, Money::minorUnits('19.50', 'NZD'));
        $this->assertSame(2400, Money::minorUnits('24', 'NZD'));
        $this->assertSame(2200, Money::minorUnits('22.00', 'GBP'));
        $this->assertSame(3000, Money::minorUnits('30.00', 'EUR'));
        $this->assertSame('19.50', Money::toDecimal(1950, 'NZD'));
    }

    public function test_zero_decimal_currency_does_not_multiply_by_one_hundred(): void
    {
        $this->assertSame(500, Money::minorUnits('500', 'JPY'));
        $this->assertSame('500', Money::toDecimal(500, 'JPY'));
    }

    public function test_fractional_zero_decimal_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::minorUnits('19.50', 'JPY');
    }

    public function test_unknown_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::minorUnits('10.00', 'XXX');
    }
}
