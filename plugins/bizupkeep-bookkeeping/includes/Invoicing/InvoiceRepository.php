<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Invoicing;

use BizHub\Bookkeeping\Contracts\InvoiceRepositoryInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\Invoice;
use BizHub\Bookkeeping\Entities\InvoiceLine;
use BizHub\Bookkeeping\Enums\InvoiceStatus;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\TransactionInterface;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for invoices and invoice
 * lines.
 *
 * findByCustomerUuid() date-filters in PHP after an equality-only
 * findAll() fetch - the same DatabaseInterface::findAll()-is-equality-
 * only pattern JournalRepository already established, which keeps this
 * class fully testable against InMemoryDatabase.
 *
 * @package BizHub\Bookkeeping\Invoicing
 */
final class InvoiceRepository implements InvoiceRepositoryInterface
{
    private const INVOICES_TABLE = 'bizhub_bookkeeping_invoices';

    private const LINES_TABLE = 'bizhub_bookkeeping_invoice_lines';

    private const NUMBER_PREFIX = 'INV-';

    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly TransactionInterface $transaction
    ) {
    }

    public function findByCompanyUuid(string $companyUuid, ?InvoiceStatus $status = null): array
    {
        $criteria = ['company_uuid' => $companyUuid];

        if ($status !== null) {
            $criteria['status'] = $status->value;
        }

        $rows = $this->database->findAll(self::INVOICES_TABLE, $criteria, ['invoice_date' => 'DESC']);

        return array_map($this->hydrateWithLines(...), $rows);
    }

    public function findByUuid(string $uuid): ?Invoice
    {
        $row = $this->database->findOne(self::INVOICES_TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrateWithLines($row);
    }

    public function findByCustomerUuid(string $customerUuid, DateRange $range): array
    {
        $rows = $this->database->findAll(
            self::INVOICES_TABLE,
            ['customer_uuid' => $customerUuid],
            ['invoice_date' => 'ASC']
        );

        $invoices = [];

        foreach ($rows as $row) {
            $invoiceDate = new DateTimeImmutable((string) $row['invoice_date']);

            if ($range->from !== null && $invoiceDate < $range->from) {
                continue;
            }

            if ($invoiceDate > $range->to) {
                continue;
            }

            $invoices[] = $this->hydrateWithLines($row);
        }

        return $invoices;
    }

    public function nextInvoiceNumber(string $companyUuid): string
    {
        $rows = $this->database->findAll(self::INVOICES_TABLE, ['company_uuid' => $companyUuid]);

        $highest = 0;

        foreach ($rows as $row) {
            $number = (string) $row['invoice_number'];

            if (! str_starts_with($number, self::NUMBER_PREFIX)) {
                continue;
            }

            $suffix = (int) substr($number, strlen(self::NUMBER_PREFIX));
            $highest = max($highest, $suffix);
        }

        return self::NUMBER_PREFIX . str_pad((string) ($highest + 1), 6, '0', STR_PAD_LEFT);
    }

    public function insertWithLines(Invoice $invoice): Invoice
    {
        return $this->transaction->transactional(function () use ($invoice): Invoice {
            $this->database->insert(self::INVOICES_TABLE, $this->dehydrateInvoice($invoice));

            foreach ($invoice->lines as $index => $line) {
                $this->database->insert(self::LINES_TABLE, $this->dehydrateLine($invoice, $line, $index));
            }

            return $invoice;
        });
    }

    public function save(Invoice $invoice): Invoice
    {
        $this->database->update(self::INVOICES_TABLE, $this->dehydrateInvoice($invoice), ['uuid' => $invoice->uuid]);

        return $invoice;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrateInvoice(Invoice $invoice): array
    {
        return [
            'uuid' => $invoice->uuid,
            'company_uuid' => $invoice->companyUuid,
            'customer_uuid' => $invoice->customerUuid,
            'invoice_number' => $invoice->invoiceNumber,
            'invoice_date' => $invoice->invoiceDate->format('Y-m-d'),
            'due_date' => $invoice->dueDate->format('Y-m-d'),
            'category_account_uuid' => $invoice->categoryAccountUuid,
            'includes_vat' => $invoice->includesVat ? 1 : 0,
            'subtotal_minor' => $invoice->subtotal->minorUnits(),
            'vat_minor' => $invoice->vat->minorUnits(),
            'total_minor' => $invoice->total->minorUnits(),
            'status' => $invoice->status->value,
            'notes' => $invoice->notes,
            'journal_entry_uuid' => $invoice->journalEntryUuid,
            'payment_journal_entry_uuid' => $invoice->paymentJournalEntryUuid,
            'sent_at' => $invoice->sentAt?->format('Y-m-d H:i:s'),
            'paid_at' => $invoice->paidAt?->format('Y-m-d H:i:s'),
            'created_at' => $invoice->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $invoice->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrateLine(Invoice $invoice, InvoiceLine $line, int $index): array
    {
        return [
            'uuid' => Uuid::generate(),
            'invoice_uuid' => $invoice->uuid,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price_minor' => $line->unitPrice->minorUnits(),
            'line_total_minor' => $line->lineTotal->minorUnits(),
            'line_order' => $index,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateWithLines(array $row): Invoice
    {
        $lineRows = $this->database->findAll(
            self::LINES_TABLE,
            ['invoice_uuid' => (string) $row['uuid']],
            ['line_order' => 'ASC']
        );

        return $this->hydrateInvoice($row)->withLines(array_map($this->hydrateLine(...), $lineRows));
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateInvoice(array $row): Invoice
    {
        return new Invoice(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            customerUuid: (string) $row['customer_uuid'],
            invoiceNumber: (string) $row['invoice_number'],
            invoiceDate: new DateTimeImmutable((string) $row['invoice_date']),
            dueDate: new DateTimeImmutable((string) $row['due_date']),
            categoryAccountUuid: (string) $row['category_account_uuid'],
            includesVat: (bool) (int) $row['includes_vat'],
            subtotal: Money::fromMinorUnits((int) $row['subtotal_minor']),
            vat: Money::fromMinorUnits((int) $row['vat_minor']),
            total: Money::fromMinorUnits((int) $row['total_minor']),
            status: InvoiceStatus::from((string) $row['status']),
            notes: (string) $row['notes'],
            journalEntryUuid: $this->nullableString($row['journal_entry_uuid'] ?? null),
            paymentJournalEntryUuid: $this->nullableString($row['payment_journal_entry_uuid'] ?? null),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
            sentAt: isset($row['sent_at']) && $row['sent_at'] !== null
                ? new DateTimeImmutable((string) $row['sent_at'])
                : null,
            paidAt: isset($row['paid_at']) && $row['paid_at'] !== null
                ? new DateTimeImmutable((string) $row['paid_at'])
                : null,
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateLine(array $row): InvoiceLine
    {
        return new InvoiceLine(
            description: (string) $row['description'],
            quantity: (int) $row['quantity'],
            unitPrice: Money::fromMinorUnits((int) $row['unit_price_minor']),
            lineTotal: Money::fromMinorUnits((int) $row['line_total_minor']),
            lineOrder: (int) $row['line_order'],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
