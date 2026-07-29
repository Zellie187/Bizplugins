<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\DTO;

use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Support\Money;
use DateTimeImmutable;

/**
 * The client-facing "capture income/expense" form's input, deliberately
 * free of any debit/credit vocabulary - TransactionCaptureService
 * translates this into a correctly-balanced journal entry (2 lines,
 * or 3 when $includesVat splits out a VAT Input/Output line).
 *
 * $amount is always treated as VAT-inclusive when $includesVat is
 * true - the standard-rate VAT portion is calculated backward out of
 * it, matching how a business owner reads a receipt/bank line.
 *
 * @package BizHub\Bookkeeping\DTO
 */
final readonly class CaptureTransactionData
{
    public function __construct(
        public DateTimeImmutable $date,
        public Money $amount,
        public string $categoryAccountUuid,
        public PaymentMethod $paymentMethod,
        public string $description,
        public bool $includesVat = false
    ) {
    }
}
