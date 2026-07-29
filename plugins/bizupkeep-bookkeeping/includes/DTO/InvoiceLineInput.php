<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\DTO;

use BizHub\Bookkeeping\Support\Money;

/**
 * One line item as submitted when creating an invoice - deliberately
 * free of the computed lineTotal an InvoiceLine entity carries, since
 * InvoiceService computes that itself.
 *
 * @package BizHub\Bookkeeping\DTO
 */
final readonly class InvoiceLineInput
{
    public function __construct(
        public string $description,
        public int $quantity,
        public Money $unitPrice
    ) {
    }
}
