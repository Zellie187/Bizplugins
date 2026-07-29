<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Reporting;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Support\Money;

/**
 * Output VAT, Input VAT and the net position for a period - the figures
 * needed to complete a VAT201 return. netVatPayable is outputVat minus
 * inputVat: positive means the company owes SARS, negative means a
 * refund is due.
 *
 * @package BizHub\Bookkeeping\Reporting
 */
final readonly class VatSummaryReport
{
    public function __construct(
        public DateRange $range,
        public Money $outputVat,
        public Money $inputVat,
        public Money $netVatPayable
    ) {
    }
}
