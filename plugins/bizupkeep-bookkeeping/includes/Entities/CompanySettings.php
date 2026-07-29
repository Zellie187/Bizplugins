<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A company's plugin-owned bookkeeping settings - currently just its
 * VAT registration status. Staff-only to set (via ManualJournalEntryPage),
 * never client-editable, since it gates whether the client-facing
 * capture form shows VAT fields at all.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class CompanySettings
{
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public bool $isVatRegistered,
        public ?string $vatNumber,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('CompanySettings uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('CompanySettings companyUuid cannot be empty.');
        }
    }
}
