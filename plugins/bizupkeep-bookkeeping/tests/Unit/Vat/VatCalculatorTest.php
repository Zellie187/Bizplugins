<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Vat;

use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Vat\VatCalculator;
use PHPUnit\Framework\TestCase;

final class VatCalculatorTest extends TestCase
{
    public function testStandardRateVatOfAWholeRandAmount(): void
    {
        $vat = VatCalculator::vatPortionOfInclusive(Money::fromRands(115.00));

        self::assertTrue($vat->equals(Money::fromRands(15.00)));
    }

    public function testStandardRateVatWithFractionalCentsRoundsToTheNearestCent(): void
    {
        $vat = VatCalculator::vatPortionOfInclusive(Money::fromRands(100.00));

        self::assertTrue($vat->equals(Money::fromRands(13.04)));
    }

    public function testSubCentInclusiveAmountRoundsTheVatPortionToZero(): void
    {
        $vat = VatCalculator::vatPortionOfInclusive(Money::fromRands(0.03));

        self::assertTrue($vat->isZero());
    }

    public function testZeroInclusiveAmountHasZeroVat(): void
    {
        self::assertTrue(VatCalculator::vatPortionOfInclusive(Money::zero())->isZero());
    }
}
