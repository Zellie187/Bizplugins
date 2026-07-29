<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Support;

use BizHub\Bookkeeping\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromRandsRoundTripsThroughMinorUnits(): void
    {
        $money = Money::fromRands(1234.56);

        self::assertSame(123456, $money->minorUnits());
        self::assertSame(1234.56, $money->toRands());
    }

    public function testFromRandsRoundsToNearestCent(): void
    {
        self::assertSame(101, Money::fromRands(1.005)->minorUnits());
        self::assertSame(100, Money::fromRands(0.999)->minorUnits());
    }

    public function testZeroIsZero(): void
    {
        self::assertTrue(Money::zero()->isZero());
        self::assertSame(0, Money::zero()->minorUnits());
    }

    public function testAdd(): void
    {
        $result = Money::fromRands(100.00)->add(Money::fromRands(50.50));

        self::assertTrue($result->equals(Money::fromRands(150.50)));
    }

    public function testSubtract(): void
    {
        $result = Money::fromRands(100.00)->subtract(Money::fromRands(30.00));

        self::assertTrue($result->equals(Money::fromRands(70.00)));
    }

    public function testNegate(): void
    {
        $result = Money::fromRands(50.00)->negate();

        self::assertTrue($result->isNegative());
        self::assertSame(-5000, $result->minorUnits());
    }

    public function testEqualsComparesByValueNotIdentity(): void
    {
        self::assertTrue(Money::fromRands(10.00)->equals(Money::fromMinorUnits(1000)));
        self::assertFalse(Money::fromRands(10.00)->equals(Money::fromRands(10.01)));
    }

    public function testIsPositiveAndIsNegative(): void
    {
        self::assertTrue(Money::fromRands(1.00)->isPositive());
        self::assertFalse(Money::fromRands(1.00)->isNegative());

        self::assertTrue(Money::fromRands(-1.00)->isNegative());
        self::assertFalse(Money::fromRands(-1.00)->isPositive());

        self::assertFalse(Money::zero()->isPositive());
        self::assertFalse(Money::zero()->isNegative());
    }

    public function testFormat(): void
    {
        self::assertSame('R 1,234.56', Money::fromRands(1234.56)->format());
        self::assertSame('R 0.00', Money::zero()->format());
    }

    public function testToStringUsesFormat(): void
    {
        self::assertSame('R 5.00', (string) Money::fromRands(5.00));
    }
}
