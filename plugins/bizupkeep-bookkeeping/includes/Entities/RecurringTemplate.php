<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\TransactionType;
use BizHub\Bookkeeping\Support\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A client-defined recurring transaction template (e.g. "R2,500 rent,
 * paid monthly by bank") - cron generates a pending RecurringOccurrence
 * each time $nextDueDate arrives, never posting directly itself. Unlike
 * a journal entry, a template is a scheduling convenience, not an audit
 * record - it can be edited or deleted outright.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class RecurringTemplate
{
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public TransactionType $transactionType,
        public Money $amount,
        public string $categoryAccountUuid,
        public PaymentMethod $paymentMethod,
        public string $description,
        public bool $includesVat,
        public RecurringFrequency $frequency,
        public DateTimeImmutable $nextDueDate,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('RecurringTemplate uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('RecurringTemplate companyUuid cannot be empty.');
        }

        if (! $this->amount->isPositive()) {
            throw new InvalidArgumentException('RecurringTemplate amount must be greater than zero.');
        }
    }

    /**
     * Advances the schedule after generateDueOccurrences() creates a
     * pending occurrence for the current nextDueDate.
     */
    public function withNextDueDate(DateTimeImmutable $nextDueDate, DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->transactionType,
            $this->amount,
            $this->categoryAccountUuid,
            $this->paymentMethod,
            $this->description,
            $this->includesVat,
            $this->frequency,
            $nextDueDate,
            $this->isActive,
            $this->createdAt,
            $updatedAt
        );
    }

    public function withActive(bool $isActive, DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->transactionType,
            $this->amount,
            $this->categoryAccountUuid,
            $this->paymentMethod,
            $this->description,
            $this->includesVat,
            $this->frequency,
            $this->nextDueDate,
            $isActive,
            $this->createdAt,
            $updatedAt
        );
    }

    public function withDetails(
        Money $amount,
        string $categoryAccountUuid,
        PaymentMethod $paymentMethod,
        string $description,
        bool $includesVat,
        RecurringFrequency $frequency,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->transactionType,
            $amount,
            $categoryAccountUuid,
            $paymentMethod,
            $description,
            $includesVat,
            $frequency,
            $this->nextDueDate,
            $this->isActive,
            $this->createdAt,
            $updatedAt
        );
    }
}
