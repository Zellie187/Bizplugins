<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Invoicing;

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Accounts\CompanySettingsRepository;
use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\DTO\InvoiceLineInput;
use BizHub\Bookkeeping\Enums\InvoiceStatus;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Bookkeeping\Invoicing\CustomerRepository;
use BizHub\Bookkeeping\Invoicing\InvoiceMailer;
use BizHub\Bookkeeping\Invoicing\InvoicePdfBuilder;
use BizHub\Bookkeeping\Invoicing\InvoiceRepository;
use BizHub\Bookkeeping\Invoicing\InvoiceService;
use BizHub\Bookkeeping\Invoicing\StatementPdfBuilder;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Reporting\FinancialStatementsService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\FakeCompanyService;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Entities\RegisteredAddress;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InvoiceServiceTest extends TestCase
{
    private const COMPANY = 'company-1';

    private AccountService $accountService;
    private SubscriptionService $subscriptionService;
    private CustomerRepository $customers;
    private InvoiceRepository $invoiceRepository;
    private FinancialStatementsService $statements;
    private InvoiceService $invoiceService;

    protected function setUp(): void
    {
        $GLOBALS['__bizupkeep_bookkeeping_test_mails'] = [];

        $database = new InMemoryDatabase();
        $accountRepository = new AccountRepository($database);
        $this->accountService = new AccountService($accountRepository);
        $this->accountService->ensureSeeded(self::COMPANY);

        $journalRepository = new JournalRepository($database, new InMemoryTransaction());
        $ledger = new LedgerService($journalRepository, $accountRepository);

        $this->subscriptionService = new SubscriptionService(new SubscriptionRepository($database));
        $this->subscriptionService->extend(self::COMPANY, 30);

        $this->customers = new CustomerRepository($database);
        $this->invoiceRepository = new InvoiceRepository($database, new InMemoryTransaction());

        $company = new Company(
            self::COMPANY,
            42,
            '2026/123456/07',
            'Test Company (Pty) Ltd',
            'Private Company',
            CompanyStatus::ACTIVE,
            new RegisteredAddress('1 Main Road', '', 'Sandton', 'Johannesburg', 'Gauteng', '2196')
        );
        $companies = new FakeCompanyService([self::COMPANY => $company]);
        $companySettings = new CompanySettingsRepository($database);

        $invoicePdfBuilder = new InvoicePdfBuilder($companies, $companySettings);
        $statementPdfBuilder = new StatementPdfBuilder($companies, $companySettings);
        $invoiceMailer = new InvoiceMailer();

        $this->invoiceService = new InvoiceService(
            $this->customers,
            $this->invoiceRepository,
            $ledger,
            $this->accountService,
            $this->subscriptionService,
            $invoicePdfBuilder,
            $statementPdfBuilder,
            $invoiceMailer
        );

        $this->statements = new FinancialStatementsService($journalRepository, $accountRepository);
    }

    private function code(string $code): string
    {
        return $this->accountService->getByCode(self::COMPANY, $code)->uuid;
    }

    private function makeCustomer(): string
    {
        $customer = $this->invoiceService->createCustomer(
            self::COMPANY,
            'Acme Traders',
            'billing@acme.example',
            '0123456789',
            '1 Main Road',
            '',
            'Sandton',
            'Johannesburg',
            'Gauteng',
            '2196'
        );

        return $customer->uuid;
    }

    public function testCreateInvoiceComputesTotalsCorrectlyWithVat(): void
    {
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            true,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Company registration service', 1, Money::fromRands(1150.00))]
        );

        self::assertSame(InvoiceStatus::Draft, $invoice->status);
        self::assertTrue($invoice->total->equals(Money::fromRands(1150.00)));
        self::assertTrue($invoice->vat->equals(Money::fromRands(150.00)));
        self::assertTrue($invoice->subtotal->equals(Money::fromRands(1000.00)));
        self::assertNull($invoice->journalEntryUuid);
    }

    public function testCreateInvoiceRejectsNoLineItems(): void
    {
        $customerUuid = $this->makeCustomer();

        $this->expectException(ValidationException::class);

        $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            false,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            []
        );
    }

    public function testSendInvoicePostsABalancedThreeLineEntryAndEmailsThePdf(): void
    {
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            true,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Company registration service', 1, Money::fromRands(1150.00))]
        );

        $sent = $this->invoiceService->sendInvoice(self::COMPANY, $invoice->uuid, actorId: 1);

        self::assertSame(InvoiceStatus::Sent, $sent->status);
        self::assertNotNull($sent->journalEntryUuid);
        self::assertNotNull($sent->sentAt);

        $sums = $this->statements->trialBalance(self::COMPANY, DateRange::sinceInception(new DateTimeImmutable('2026-08-31')));
        self::assertTrue($sums->isBalanced());

        self::assertCount(1, $GLOBALS['__bizupkeep_bookkeeping_test_mails']);
        $mail = $GLOBALS['__bizupkeep_bookkeeping_test_mails'][0];
        self::assertSame('billing@acme.example', $mail['to']);
        self::assertCount(1, $mail['attachments']);
        self::assertGreaterThan(0, $mail['attachmentSizes'][0]);
    }

    public function testSendInvoiceIsRejectedWhenSubscriptionInactive(): void
    {
        $this->subscriptionService->suspend(self::COMPANY);
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            false,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Consulting', 1, Money::fromRands(500.00))]
        );

        $this->expectException(SubscriptionInactiveException::class);
        $this->invoiceService->sendInvoice(self::COMPANY, $invoice->uuid, actorId: 1);
    }

    public function testRecordPaymentClearsAccountsReceivable(): void
    {
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            false,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Consulting', 2, Money::fromRands(500.00))]
        );

        $sent = $this->invoiceService->sendInvoice(self::COMPANY, $invoice->uuid, actorId: 1);
        $paid = $this->invoiceService->recordPayment(self::COMPANY, $sent->uuid, PaymentMethod::Bank, actorId: 1);

        self::assertSame(InvoiceStatus::Paid, $paid->status);
        self::assertNotNull($paid->paymentJournalEntryUuid);

        $report = $this->statements->trialBalance(
            self::COMPANY,
            DateRange::sinceInception(new DateTimeImmutable('2026-08-31'))
        );
        self::assertTrue($report->isBalanced());

        $arRow = null;
        foreach ($report->rows as $row) {
            if ($row->code === ChartOfAccountsTemplate::CODE_ACCOUNTS_RECEIVABLE) {
                $arRow = $row;
            }
        }
        self::assertNotNull($arRow);
        self::assertTrue($arRow->net->isZero(), 'AR should net to zero once the invoice is paid.');
        self::assertTrue($arRow->debit->equals($sent->total));
        self::assertTrue($arRow->credit->equals($sent->total));
    }

    public function testRecordPaymentFailsForANonSentInvoice(): void
    {
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            false,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Consulting', 1, Money::fromRands(500.00))]
        );

        $this->expectException(ValidationException::class);
        $this->invoiceService->recordPayment(self::COMPANY, $invoice->uuid, PaymentMethod::Bank, actorId: 1);
    }

    public function testVoidInvoiceOnlyWorksOnADraft(): void
    {
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            false,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Consulting', 1, Money::fromRands(500.00))]
        );

        $voided = $this->invoiceService->voidInvoice(self::COMPANY, $invoice->uuid);
        self::assertSame(InvoiceStatus::Void, $voided->status);

        $sentInvoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            false,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Consulting', 1, Money::fromRands(500.00))]
        );
        $this->invoiceService->sendInvoice(self::COMPANY, $sentInvoice->uuid, actorId: 1);

        $this->expectException(ValidationException::class);
        $this->invoiceService->voidInvoice(self::COMPANY, $sentInvoice->uuid);
    }

    public function testFullLifecycleKeepsTheTrialBalanceBalanced(): void
    {
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            true,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            'Thanks for your business',
            [
                new InvoiceLineInput('Company registration service', 1, Money::fromRands(1150.00)),
                new InvoiceLineInput('Filing fee', 2, Money::fromRands(57.50)),
            ]
        );

        $sent = $this->invoiceService->sendInvoice(self::COMPANY, $invoice->uuid, actorId: 1);
        $this->invoiceService->recordPayment(self::COMPANY, $sent->uuid, PaymentMethod::Bank, actorId: 1);

        $report = $this->statements->trialBalance(
            self::COMPANY,
            DateRange::sinceInception(new DateTimeImmutable('2026-08-31'))
        );

        self::assertTrue($report->isBalanced());
    }

    public function testResendInvoiceEmailsAgainWithoutReposting(): void
    {
        $customerUuid = $this->makeCustomer();

        $invoice = $this->invoiceService->createInvoice(
            self::COMPANY,
            $customerUuid,
            $this->code(ChartOfAccountsTemplate::CODE_SALES_REVENUE),
            false,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-15'),
            '',
            [new InvoiceLineInput('Consulting', 1, Money::fromRands(500.00))]
        );

        $sent = $this->invoiceService->sendInvoice(self::COMPANY, $invoice->uuid, actorId: 1);
        self::assertCount(1, $GLOBALS['__bizupkeep_bookkeeping_test_mails']);

        $this->invoiceService->resendInvoice(self::COMPANY, $sent->uuid);
        self::assertCount(2, $GLOBALS['__bizupkeep_bookkeeping_test_mails']);

        $stillSent = $this->invoiceService->listInvoices(self::COMPANY, InvoiceStatus::Sent);
        self::assertCount(1, $stillSent);
    }
}
