<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Invoicing;

use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Entities\Customer;
use BizHub\Bookkeeping\Entities\Invoice;
use BizHub\Bookkeeping\Entities\InvoiceLine;
use BizHub\Bookkeeping\Enums\InvoiceStatus;
use BizHub\Bookkeeping\Invoicing\CustomerRepository;
use BizHub\Bookkeeping\Invoicing\InvoiceRepository;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RepositoriesTest extends TestCase
{
    private const COMPANY = 'company-1';

    private InMemoryDatabase $database;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
    }

    private function makeCustomer(?string $name = null): Customer
    {
        return new Customer(
            uuid: Uuid::generate(),
            companyUuid: self::COMPANY,
            name: $name ?? 'Acme Traders',
            email: 'billing@acme.example',
            phone: '0123456789',
            addressLine1: '1 Main Road',
            addressLine2: '',
            suburb: 'Sandton',
            city: 'Johannesburg',
            province: 'Gauteng',
            postalCode: '2196',
            createdAt: new DateTimeImmutable(),
        );
    }

    public function testSaveThenFindByUuidRoundTripsACustomer(): void
    {
        $repository = new CustomerRepository($this->database);
        $customer = $this->makeCustomer();

        $repository->save($customer);

        $found = $repository->findByUuid($customer->uuid);

        self::assertNotNull($found);
        self::assertSame('Acme Traders', $found->name);
        self::assertSame('billing@acme.example', $found->email);
    }

    public function testFindByCompanyUuidReturnsOnlyThatCompanysCustomers(): void
    {
        $repository = new CustomerRepository($this->database);

        $repository->save($this->makeCustomer());
        $repository->save(new Customer(
            uuid: Uuid::generate(),
            companyUuid: 'company-2',
            name: 'Other Co',
            email: '',
            phone: '',
            addressLine1: '',
            addressLine2: '',
            suburb: '',
            city: '',
            province: '',
            postalCode: '',
            createdAt: new DateTimeImmutable(),
        ));

        self::assertCount(1, $repository->findByCompanyUuid(self::COMPANY));
    }

    public function testDeleteRemovesTheCustomer(): void
    {
        $repository = new CustomerRepository($this->database);
        $customer = $this->makeCustomer();
        $repository->save($customer);

        $repository->delete($customer->uuid);

        self::assertNull($repository->findByUuid($customer->uuid));
    }

    private function makeInvoice(
        string $customerUuid,
        string $invoiceNumber,
        InvoiceStatus $status = InvoiceStatus::Draft,
        ?DateTimeImmutable $invoiceDate = null
    ): Invoice {
        $line = new InvoiceLine(
            description: 'Company registration service',
            quantity: 1,
            unitPrice: Money::fromRands(1000.00),
            lineTotal: Money::fromRands(1000.00),
            lineOrder: 0,
        );

        return new Invoice(
            uuid: Uuid::generate(),
            companyUuid: self::COMPANY,
            customerUuid: $customerUuid,
            invoiceNumber: $invoiceNumber,
            invoiceDate: $invoiceDate ?? new DateTimeImmutable('2026-08-01'),
            dueDate: new DateTimeImmutable('2026-08-15'),
            categoryAccountUuid: 'account-sales',
            includesVat: false,
            subtotal: Money::fromRands(1000.00),
            vat: Money::zero(),
            total: Money::fromRands(1000.00),
            status: $status,
            notes: '',
            journalEntryUuid: null,
            paymentJournalEntryUuid: null,
            createdAt: new DateTimeImmutable(),
            lines: [$line],
        );
    }

    public function testInsertWithLinesThenFindByUuidRoundTripsAnInvoiceAndItsLines(): void
    {
        $repository = new InvoiceRepository($this->database, new InMemoryTransaction());
        $invoice = $this->makeInvoice('customer-1', 'INV-000001');

        $repository->insertWithLines($invoice);

        $found = $repository->findByUuid($invoice->uuid);

        self::assertNotNull($found);
        self::assertSame('INV-000001', $found->invoiceNumber);
        self::assertCount(1, $found->lines);
        self::assertSame('Company registration service', $found->lines[0]->description);
        self::assertTrue($found->lines[0]->lineTotal->equals(Money::fromRands(1000.00)));
    }

    public function testNextInvoiceNumberStartsAtOneAndIncrementsFromTheHighestExisting(): void
    {
        $repository = new InvoiceRepository($this->database, new InMemoryTransaction());

        self::assertSame('INV-000001', $repository->nextInvoiceNumber(self::COMPANY));

        $repository->insertWithLines($this->makeInvoice('customer-1', 'INV-000001'));
        self::assertSame('INV-000002', $repository->nextInvoiceNumber(self::COMPANY));

        $repository->insertWithLines($this->makeInvoice('customer-1', 'INV-000005'));
        self::assertSame('INV-000006', $repository->nextInvoiceNumber(self::COMPANY));
    }

    public function testNextInvoiceNumberIsScopedPerCompany(): void
    {
        $repository = new InvoiceRepository($this->database, new InMemoryTransaction());

        $otherCompanyInvoice = $this->makeInvoice('customer-1', 'INV-000001');
        $repository->insertWithLines(new Invoice(
            uuid: $otherCompanyInvoice->uuid,
            companyUuid: 'company-2',
            customerUuid: $otherCompanyInvoice->customerUuid,
            invoiceNumber: $otherCompanyInvoice->invoiceNumber,
            invoiceDate: $otherCompanyInvoice->invoiceDate,
            dueDate: $otherCompanyInvoice->dueDate,
            categoryAccountUuid: $otherCompanyInvoice->categoryAccountUuid,
            includesVat: false,
            subtotal: $otherCompanyInvoice->subtotal,
            vat: Money::zero(),
            total: $otherCompanyInvoice->total,
            status: InvoiceStatus::Draft,
            notes: '',
            journalEntryUuid: null,
            paymentJournalEntryUuid: null,
            createdAt: new DateTimeImmutable(),
            lines: $otherCompanyInvoice->lines,
        ));

        self::assertSame('INV-000001', $repository->nextInvoiceNumber(self::COMPANY));
    }

    public function testSaveUpdatesTheInvoiceHeaderOnly(): void
    {
        $repository = new InvoiceRepository($this->database, new InMemoryTransaction());
        $invoice = $this->makeInvoice('customer-1', 'INV-000001');
        $repository->insertWithLines($invoice);

        $sent = $invoice->withSent('entry-1', new DateTimeImmutable('2026-08-02'));
        $repository->save($sent);

        $found = $repository->findByUuid($invoice->uuid);
        self::assertNotNull($found);
        self::assertSame(InvoiceStatus::Sent, $found->status);
        self::assertSame('entry-1', $found->journalEntryUuid);
        self::assertCount(1, $found->lines);
    }

    public function testFindByCompanyUuidFiltersByStatus(): void
    {
        $repository = new InvoiceRepository($this->database, new InMemoryTransaction());

        $draft = $this->makeInvoice('customer-1', 'INV-000001', InvoiceStatus::Draft);
        $sent = $this->makeInvoice('customer-1', 'INV-000002', InvoiceStatus::Sent);
        $repository->insertWithLines($draft);
        $repository->insertWithLines($sent);

        $draftsOnly = $repository->findByCompanyUuid(self::COMPANY, InvoiceStatus::Draft);

        self::assertCount(1, $draftsOnly);
        self::assertSame('INV-000001', $draftsOnly[0]->invoiceNumber);
    }

    public function testFindByCustomerUuidFiltersByDateRange(): void
    {
        $repository = new InvoiceRepository($this->database, new InMemoryTransaction());

        $repository->insertWithLines(
            $this->makeInvoice('customer-1', 'INV-000001', InvoiceStatus::Sent, new DateTimeImmutable('2026-06-01'))
        );
        $repository->insertWithLines(
            $this->makeInvoice('customer-1', 'INV-000002', InvoiceStatus::Sent, new DateTimeImmutable('2026-08-01'))
        );

        $inRange = $repository->findByCustomerUuid(
            'customer-1',
            DateRange::between(new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-08-31'))
        );

        self::assertCount(1, $inRange);
        self::assertSame('INV-000002', $inRange[0]->invoiceNumber);
    }
}
