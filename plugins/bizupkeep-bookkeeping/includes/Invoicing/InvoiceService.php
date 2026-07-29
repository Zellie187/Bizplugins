<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Invoicing;

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Contracts\CustomerRepositoryInterface;
use BizHub\Bookkeeping\Contracts\InvoiceRepositoryInterface;
use BizHub\Bookkeeping\Contracts\InvoiceServiceInterface;
use BizHub\Bookkeeping\Contracts\LedgerServiceInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\DTO\InvoiceLineInput;
use BizHub\Bookkeeping\DTO\JournalLineData;
use BizHub\Bookkeeping\DTO\ManualJournalEntryData;
use BizHub\Bookkeeping\Entities\Customer;
use BizHub\Bookkeeping\Entities\Invoice;
use BizHub\Bookkeeping\Entities\InvoiceLine;
use BizHub\Bookkeeping\Enums\InvoiceStatus;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Vat\VatCalculator;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;

/**
 * Public API for customers, invoices, and statements.
 *
 * Sending an invoice or recording a payment always delegates to
 * LedgerServiceInterface::postEntry() directly, the same layer
 * ManualJournalEntryPage already uses - invoicing is a genuinely
 * different economic event shape (AR-based recognition at issuance, a
 * separate cash-receipt entry at payment) than the simplified "capture"
 * TransactionCaptureService handles, so it gets its own translation
 * layer rather than being forced through that one.
 *
 * Line prices are treated as VAT-inclusive, split backward via the
 * same Vat\VatCalculator the rest of this plugin already uses - one
 * consistent VAT mental model across Capture, Recurring and Invoicing.
 *
 * @package BizHub\Bookkeeping\Invoicing
 */
final class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly LedgerServiceInterface $ledger,
        private readonly AccountServiceInterface $accounts,
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly InvoicePdfBuilder $invoicePdfBuilder,
        private readonly StatementPdfBuilder $statementPdfBuilder,
        private readonly InvoiceMailer $invoiceMailer
    ) {
    }

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
    ): Customer {
        return $this->customers->save(new Customer(
            uuid: Uuid::generate(),
            companyUuid: $companyUuid,
            name: $name,
            email: $email,
            phone: $phone,
            addressLine1: $addressLine1,
            addressLine2: $addressLine2,
            suburb: $suburb,
            city: $city,
            province: $province,
            postalCode: $postalCode,
            createdAt: new DateTimeImmutable(),
        ));
    }

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
    ): Customer {
        $existing = $this->ownedCustomer($companyUuid, $customerUuid);

        return $this->customers->save(new Customer(
            uuid: $existing->uuid,
            companyUuid: $existing->companyUuid,
            name: $name,
            email: $email,
            phone: $phone,
            addressLine1: $addressLine1,
            addressLine2: $addressLine2,
            suburb: $suburb,
            city: $city,
            province: $province,
            postalCode: $postalCode,
            createdAt: $existing->createdAt,
            updatedAt: new DateTimeImmutable(),
        ));
    }

    public function deleteCustomer(string $companyUuid, string $customerUuid): void
    {
        $existing = $this->ownedCustomer($companyUuid, $customerUuid);

        $this->customers->delete($existing->uuid);
    }

    public function listCustomers(string $companyUuid): array
    {
        return $this->customers->findByCompanyUuid($companyUuid);
    }

    public function createInvoice(
        string $companyUuid,
        string $customerUuid,
        string $categoryAccountUuid,
        bool $includesVat,
        DateTimeImmutable $invoiceDate,
        DateTimeImmutable $dueDate,
        string $notes,
        array $lines
    ): Invoice {
        $this->ownedCustomer($companyUuid, $customerUuid);

        if ($lines === []) {
            throw new ValidationException('An invoice needs at least one line item.');
        }

        $invoiceLines = [];
        $total = Money::zero();

        foreach ($lines as $index => $lineInput) {
            if (! $lineInput instanceof InvoiceLineInput) {
                throw new ValidationException('Invalid invoice line input.');
            }

            $lineTotal = Money::fromMinorUnits($lineInput->unitPrice->minorUnits() * $lineInput->quantity);
            $total = $total->add($lineTotal);

            $invoiceLines[] = new InvoiceLine(
                description: $lineInput->description,
                quantity: $lineInput->quantity,
                unitPrice: $lineInput->unitPrice,
                lineTotal: $lineTotal,
                lineOrder: $index,
            );
        }

        if (! $total->isPositive()) {
            throw new ValidationException('Invoice total must be greater than zero.');
        }

        $vat = $includesVat ? VatCalculator::vatPortionOfInclusive($total) : Money::zero();
        $subtotal = $total->subtract($vat);

        $invoice = new Invoice(
            uuid: Uuid::generate(),
            companyUuid: $companyUuid,
            customerUuid: $customerUuid,
            invoiceNumber: $this->invoices->nextInvoiceNumber($companyUuid),
            invoiceDate: $invoiceDate,
            dueDate: $dueDate,
            categoryAccountUuid: $categoryAccountUuid,
            includesVat: $includesVat && $vat->isPositive(),
            subtotal: $subtotal,
            vat: $vat,
            total: $total,
            status: InvoiceStatus::Draft,
            notes: $notes,
            journalEntryUuid: null,
            paymentJournalEntryUuid: null,
            createdAt: new DateTimeImmutable(),
            lines: $invoiceLines,
        );

        return $this->invoices->insertWithLines($invoice);
    }

    public function sendInvoice(string $companyUuid, string $invoiceUuid, int $actorId): Invoice
    {
        $this->assertActiveSubscription($companyUuid);
        $invoice = $this->ownedDraftInvoice($companyUuid, $invoiceUuid);
        $customer = $this->ownedCustomer($companyUuid, $invoice->customerUuid);

        $arAccount = $this->accounts->getByCode($companyUuid, ChartOfAccountsTemplate::CODE_ACCOUNTS_RECEIVABLE);

        $lines = [
            JournalLineData::debit($arAccount->uuid, $invoice->total),
            JournalLineData::credit($invoice->categoryAccountUuid, $invoice->subtotal),
        ];

        if ($invoice->vat->isPositive()) {
            $vatOutputAccount = $this->accounts->getByCode($companyUuid, ChartOfAccountsTemplate::CODE_VAT_OUTPUT);
            $lines[] = JournalLineData::credit($vatOutputAccount->uuid, $invoice->vat);
        }

        $entry = $this->ledger->postEntry(
            new ManualJournalEntryData(
                companyUuid: $companyUuid,
                date: $invoice->invoiceDate,
                description: sprintf('Invoice %s to %s', $invoice->invoiceNumber, $customer->name),
                lines: $lines,
                createdBy: $actorId,
            ),
            JournalSource::InvoiceIssued
        );

        $sent = $this->invoices->save($invoice->withSent($entry->uuid, new DateTimeImmutable()));

        $pdf = $this->invoicePdfBuilder->build($companyUuid, $customer, $sent);
        $this->invoiceMailer->sendInvoice($customer, $sent, $pdf);

        return $sent;
    }

    public function resendInvoice(string $companyUuid, string $invoiceUuid): void
    {
        $invoice = $this->ownedInvoice($companyUuid, $invoiceUuid);

        if ($invoice->status === InvoiceStatus::Draft) {
            throw new ValidationException('This invoice has not been sent yet.');
        }

        $customer = $this->ownedCustomer($companyUuid, $invoice->customerUuid);
        $pdf = $this->invoicePdfBuilder->build($companyUuid, $customer, $invoice);
        $this->invoiceMailer->sendInvoice($customer, $invoice, $pdf);
    }

    public function recordPayment(
        string $companyUuid,
        string $invoiceUuid,
        PaymentMethod $paymentMethod,
        int $actorId
    ): Invoice {
        $this->assertActiveSubscription($companyUuid);
        $invoice = $this->ownedInvoice($companyUuid, $invoiceUuid);

        if ($invoice->status !== InvoiceStatus::Sent) {
            throw new ValidationException('Only a sent invoice can be marked paid.');
        }

        $arAccount = $this->accounts->getByCode($companyUuid, ChartOfAccountsTemplate::CODE_ACCOUNTS_RECEIVABLE);
        $offsetAccount = $this->accounts->getByCode($companyUuid, $paymentMethod->accountCode());

        $entry = $this->ledger->postEntry(
            new ManualJournalEntryData(
                companyUuid: $companyUuid,
                date: new DateTimeImmutable(),
                description: sprintf('Payment received for invoice %s', $invoice->invoiceNumber),
                lines: [
                    JournalLineData::debit($offsetAccount->uuid, $invoice->total),
                    JournalLineData::credit($arAccount->uuid, $invoice->total),
                ],
                createdBy: $actorId,
            ),
            JournalSource::InvoicePaymentReceived
        );

        return $this->invoices->save($invoice->withPaid($entry->uuid, new DateTimeImmutable()));
    }

    public function voidInvoice(string $companyUuid, string $invoiceUuid): Invoice
    {
        $invoice = $this->ownedInvoice($companyUuid, $invoiceUuid);

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ValidationException('Only a draft invoice can be voided.');
        }

        return $this->invoices->save($invoice->withVoid(new DateTimeImmutable()));
    }

    public function listInvoices(string $companyUuid, ?InvoiceStatus $status = null): array
    {
        return $this->invoices->findByCompanyUuid($companyUuid, $status);
    }

    public function generateInvoicePdf(string $companyUuid, string $invoiceUuid): string
    {
        $invoice = $this->ownedInvoice($companyUuid, $invoiceUuid);
        $customer = $this->ownedCustomer($companyUuid, $invoice->customerUuid);

        return $this->invoicePdfBuilder->build($companyUuid, $customer, $invoice);
    }

    public function generateStatementPdf(string $companyUuid, string $customerUuid, DateRange $range): string
    {
        $customer = $this->ownedCustomer($companyUuid, $customerUuid);
        $invoiceList = $this->invoices->findByCustomerUuid($customerUuid, $range);

        return $this->statementPdfBuilder->build($companyUuid, $customer, $range, $invoiceList);
    }

    private function ownedCustomer(string $companyUuid, string $customerUuid): Customer
    {
        $customer = $this->customers->findByUuid($customerUuid);

        if ($customer === null || $customer->companyUuid !== $companyUuid) {
            throw new ValidationException(sprintf('No customer "%s" found for this company.', $customerUuid));
        }

        return $customer;
    }

    private function ownedInvoice(string $companyUuid, string $invoiceUuid): Invoice
    {
        $invoice = $this->invoices->findByUuid($invoiceUuid);

        if ($invoice === null || $invoice->companyUuid !== $companyUuid) {
            throw new ValidationException(sprintf('No invoice "%s" found for this company.', $invoiceUuid));
        }

        return $invoice;
    }

    private function ownedDraftInvoice(string $companyUuid, string $invoiceUuid): Invoice
    {
        $invoice = $this->ownedInvoice($companyUuid, $invoiceUuid);

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ValidationException('Only a draft invoice can be sent.');
        }

        return $invoice;
    }

    private function assertActiveSubscription(string $companyUuid): void
    {
        if (! $this->subscriptions->isActive($companyUuid)) {
            throw SubscriptionInactiveException::forCompany($companyUuid);
        }
    }
}
