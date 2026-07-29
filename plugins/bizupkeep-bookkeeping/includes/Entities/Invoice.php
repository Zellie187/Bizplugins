<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Entities;

use BizHub\Bookkeeping\Enums\InvoiceStatus;
use BizHub\Bookkeeping\Support\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A client's invoice to one of their own customers. Draft until
 * sent - sending is the point it becomes a real, immutable ledger
 * posting (see InvoiceService::sendInvoice()), same "posted entries
 * are never edited in place" principle JournalEntry already follows.
 *
 * @package BizHub\Bookkeeping\Entities
 */
final readonly class Invoice
{
    /**
     * @param InvoiceLine[] $lines
     */
    public function __construct(
        public string $uuid,
        public string $companyUuid,
        public string $customerUuid,
        public string $invoiceNumber,
        public DateTimeImmutable $invoiceDate,
        public DateTimeImmutable $dueDate,
        public string $categoryAccountUuid,
        public bool $includesVat,
        public Money $subtotal,
        public Money $vat,
        public Money $total,
        public InvoiceStatus $status,
        public string $notes,
        public ?string $journalEntryUuid,
        public ?string $paymentJournalEntryUuid,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $sentAt = null,
        public ?DateTimeImmutable $paidAt = null,
        public array $lines = []
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Invoice uuid cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('Invoice companyUuid cannot be empty.');
        }

        if ($this->invoiceNumber === '') {
            throw new InvalidArgumentException('Invoice invoiceNumber cannot be empty.');
        }
    }

    /**
     * @param InvoiceLine[] $lines
     */
    public function withLines(array $lines): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->customerUuid,
            $this->invoiceNumber,
            $this->invoiceDate,
            $this->dueDate,
            $this->categoryAccountUuid,
            $this->includesVat,
            $this->subtotal,
            $this->vat,
            $this->total,
            $this->status,
            $this->notes,
            $this->journalEntryUuid,
            $this->paymentJournalEntryUuid,
            $this->createdAt,
            $this->updatedAt,
            $this->sentAt,
            $this->paidAt,
            $lines
        );
    }

    public function withSent(string $journalEntryUuid, DateTimeImmutable $sentAt): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->customerUuid,
            $this->invoiceNumber,
            $this->invoiceDate,
            $this->dueDate,
            $this->categoryAccountUuid,
            $this->includesVat,
            $this->subtotal,
            $this->vat,
            $this->total,
            InvoiceStatus::Sent,
            $this->notes,
            $journalEntryUuid,
            $this->paymentJournalEntryUuid,
            $this->createdAt,
            $sentAt,
            $sentAt,
            $this->paidAt,
            $this->lines
        );
    }

    public function withPaid(string $paymentJournalEntryUuid, DateTimeImmutable $paidAt): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->customerUuid,
            $this->invoiceNumber,
            $this->invoiceDate,
            $this->dueDate,
            $this->categoryAccountUuid,
            $this->includesVat,
            $this->subtotal,
            $this->vat,
            $this->total,
            InvoiceStatus::Paid,
            $this->notes,
            $this->journalEntryUuid,
            $paymentJournalEntryUuid,
            $this->createdAt,
            $paidAt,
            $this->sentAt,
            $paidAt,
            $this->lines
        );
    }

    public function withVoid(DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->uuid,
            $this->companyUuid,
            $this->customerUuid,
            $this->invoiceNumber,
            $this->invoiceDate,
            $this->dueDate,
            $this->categoryAccountUuid,
            $this->includesVat,
            $this->subtotal,
            $this->vat,
            $this->total,
            InvoiceStatus::Void,
            $this->notes,
            $this->journalEntryUuid,
            $this->paymentJournalEntryUuid,
            $this->createdAt,
            $updatedAt,
            $this->sentAt,
            $this->paidAt,
            $this->lines
        );
    }
}
