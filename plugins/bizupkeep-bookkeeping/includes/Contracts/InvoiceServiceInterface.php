<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\DTO\InvoiceLineInput;
use BizHub\Bookkeeping\Entities\Customer;
use BizHub\Bookkeeping\Entities\Invoice;
use BizHub\Bookkeeping\Enums\InvoiceStatus;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use DateTimeImmutable;

/**
 * Public API for customers, invoices, and statements. Sending an
 * invoice or recording a payment always delegates to
 * LedgerServiceInterface::postEntry() directly - a genuinely different
 * economic event shape (AR-based recognition) than the simplified
 * "capture" TransactionCaptureServiceInterface handles, so it gets its
 * own translation layer rather than being forced through that one.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface InvoiceServiceInterface
{
    public function createCustomer(
        string $companyUuid,
        string $name,
        string $email,
        string $phone,
        string $addressLine1,
        string $addressLine2,
        string $suburb,
        string $city,
        string $province,
        string $postalCode
    ): Customer;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function updateCustomer(
        string $companyUuid,
        string $customerUuid,
        string $name,
        string $email,
        string $phone,
        string $addressLine1,
        string $addressLine2,
        string $suburb,
        string $city,
        string $province,
        string $postalCode
    ): Customer;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function deleteCustomer(string $companyUuid, string $customerUuid): void;

    /**
     * @return Customer[]
     */
    public function listCustomers(string $companyUuid): array;

    /**
     * Builds a Draft invoice with computed subtotal/VAT/total - never
     * posts to the ledger. A draft can be freely voided or simply left
     * unsent.
     *
     * @param InvoiceLineInput[] $lines
     *
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function createInvoice(
        string $companyUuid,
        string $customerUuid,
        string $categoryAccountUuid,
        bool $includesVat,
        DateTimeImmutable $invoiceDate,
        DateTimeImmutable $dueDate,
        string $notes,
        array $lines
    ): Invoice;

    /**
     * Posts Debit AR / Credit category [/ Credit VAT Output], generates
     * the invoice PDF, and emails it to the customer.
     *
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     * @throws \BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException
     */
    public function sendInvoice(string $companyUuid, string $invoiceUuid, int $actorId): Invoice;

    /**
     * Re-generates the PDF and re-emails an already-Sent invoice -
     * never posts anything.
     *
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function resendInvoice(string $companyUuid, string $invoiceUuid): void;

    /**
     * Posts Debit Bank/Cash / Credit AR for the invoice's total.
     *
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     * @throws \BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException
     */
    public function recordPayment(
        string $companyUuid,
        string $invoiceUuid,
        PaymentMethod $paymentMethod,
        int $actorId
    ): Invoice;

    /**
     * Draft only - a Sent/Paid invoice is already a real, immutable
     * ledger posting and cannot be voided.
     *
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function voidInvoice(string $companyUuid, string $invoiceUuid): Invoice;

    /**
     * @return Invoice[]
     */
    public function listInvoices(string $companyUuid, ?InvoiceStatus $status = null): array;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function generateInvoicePdf(string $companyUuid, string $invoiceUuid): string;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function generateStatementPdf(string $companyUuid, string $customerUuid, DateRange $range): string;
}
