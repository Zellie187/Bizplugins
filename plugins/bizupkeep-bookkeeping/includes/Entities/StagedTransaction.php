<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Enums\StagedTransactionStatus;
use BizHub\Bookkeeping\Support\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One row of an imported bank statement, awaiting review before it
 * becomes a real journal entry.
 *
 * $amount is signed (positive = inflow/income-shaped, negative =
 * outflow/expense-shaped) - the same convention already used by
 * Export\AbstractBankStatementExporter's net-amount calculation, not a
 * new mental model introduced by this module. Categorizing a row takes
 * its absolute value plus this sign to decide income vs expense - see
 * BankImportService::categorize().
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class StagedTransaction
{
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public string $sourceAccountUuid,
        public DateTimeImmutable $transactionDate,
        public string $description,
        public Money $amount,
        public string $rowHash,
        public StagedTransactionStatus $status,
        public ?string $categoryAccountUuid,
        public ?string $journalEntryUuid,
        public DateTimeImmutable $importedAt,
        public ?DateTimeImmutable $categorizedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('StagedTransaction uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('StagedTransaction companyUuid cannot be empty.');
        }

        if ($this->amount->isZero()) {
            throw new InvalidArgumentException('StagedTransaction amount cannot be zero.');
        }
    }

    public function isIncomeShaped(): bool
    {
        return $this->amount->isPositive();
    }
}
