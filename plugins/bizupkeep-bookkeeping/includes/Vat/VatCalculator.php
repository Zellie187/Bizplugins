<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Vat;

use BizHub\Bookkeeping\Support\Money;

/**
 * Splits a VAT-inclusive amount into its VAT portion, at South Africa's
 * standard VAT rate. Only the standard rate is supported for v1 -
 * zero-rated/exempt line-item categorization is a real VAT201 nuance
 * left for a future round; every such line simply stays "no VAT" for
 * now, which is the correct amount, just not broken into its own
 * VAT201 category yet.
 *
 * @package BizHub\Bookkeeping\Vat
 */
final class VatCalculator
{
    public const STANDARD_RATE_PERCENT = 15.0;

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Given a VAT-inclusive (gross) amount, e.g. R115.00 on a receipt,
     * return the VAT portion embedded in it (R15.00 at the standard
     * rate) - the amount actually owed to/reclaimable from SARS.
     */
    public static function vatPortionOfInclusive(Money $inclusiveAmount): Money
    {
        $vatMinor = (int) round(
            $inclusiveAmount->minorUnits() * self::STANDARD_RATE_PERCENT / (100 + self::STANDARD_RATE_PERCENT)
        );

        return Money::fromMinorUnits($vatMinor);
    }
}
