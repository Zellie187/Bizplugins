<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\Invoice;
use BizHub\Bookkeeping\Enums\InvoiceStatus;

/**
 * Persistence contract for invoices and their line items. The only
 * class allowed to touch DatabaseInterface for these tables.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface InvoiceRepositoryInterface
{
    /**
     * @return Invoice[]
     */
    public function findByCompanyUuid(string $companyUuid, ?InvoiceStatus $status = null): array;

    public function findByUuid(string $uuid): ?Invoice;

    /**
     * @return Invoice[]
     */
    public function findByCustomerUuid(string $customerUuid, DateRange $range): array;

    /**
     * The next sequential invoice number for a company, e.g.
     * "INV-000123" - computed from the highest existing number for
     * that company, not a separately maintained counter.
     */
    public function nextInvoiceNumber(string $companyUuid): string;

    public function insertWithLines(Invoice $invoice): Invoice;

    /**
     * Updates the invoice header/status only - line items are
     * immutable once inserted (a Draft is edited by deleting and
     * recreating it, never by mutating individual lines).
     */
    public function save(Invoice $invoice): Invoice;
}
