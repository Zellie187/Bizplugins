<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Enums\ImportAmountStyle;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A company's saved bank-statement column mapping, reused on every
 * import so a client only has to map their bank's CSV columns once,
 * not every month.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class ImportMapping
{
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public string $dateColumn,
        public string $descriptionColumn,
        public ImportAmountStyle $amountStyle,
        public ?string $amountColumn,
        public ?string $debitColumn,
        public ?string $creditColumn,
        public string $dateFormat,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('ImportMapping uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('ImportMapping companyUuid cannot be empty.');
        }

        if ($this->amountStyle === ImportAmountStyle::Signed && $this->amountColumn === null) {
            throw new InvalidArgumentException('A signed-amount-style mapping requires an amount column.');
        }

        $isIncompleteDebitCredit = $this->amountStyle === ImportAmountStyle::DebitCredit
            && ($this->debitColumn === null || $this->creditColumn === null);

        if ($isIncompleteDebitCredit) {
            throw new InvalidArgumentException(
                'A debit/credit-style mapping requires both a debit and a credit column.'
            );
        }
    }
}
