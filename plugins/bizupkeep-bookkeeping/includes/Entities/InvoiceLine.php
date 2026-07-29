<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Support\Money;
use InvalidArgumentException;

/**
 * One line item of an invoice: a description, a whole-unit quantity,
 * and a unit price. Fractional quantities (e.g. part-hours of billed
 * time) are a real but deliberately out-of-scope nuance for this
 * fixed-fee-service use case.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class InvoiceLine
{
    public function __construct(
        public string $description,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
        public int $lineOrder
    ) {
        if (trim($this->description) === '') {
            throw new InvalidArgumentException('InvoiceLine description cannot be empty.');
        }

        if ($this->quantity < 1) {
            throw new InvalidArgumentException('InvoiceLine quantity must be at least 1.');
        }
    }
}
