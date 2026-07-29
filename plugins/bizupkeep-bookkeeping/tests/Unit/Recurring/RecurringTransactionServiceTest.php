<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Recurring;

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Accounts\CompanySettingsRepository;
use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\TransactionType;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Ledger\TransactionCaptureService;
use BizHub\Bookkeeping\Recurring\RecurringOccurrenceRepository;
use BizHub\Bookkeeping\Recurring\RecurringTemplateRepository;
use BizHub\Bookkeeping\Recurring\RecurringTransactionService;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\FakeClientRepository;
use BizHub\Bookkeeping\Tests\Mocks\FakeCompanyService;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryTransaction;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Entities\Profile;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Entities\RegisteredAddress;
use BizHub\Notifications\NotificationQueue;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RecurringTransactionServiceTest extends TestCase
{
    private const COMPANY = 'company-1';
    private const CLIENT_ID = 42;
    private const WP_USER_ID = 7;

    private InMemoryDatabase $database;
    private AccountService $accountService;
    private RecurringTemplateRepository $templates;
    private RecurringOccurrenceRepository $occurrences;
    private RecurringTransactionService $recurring;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();

        $accountRepository = new AccountRepository($this->database);
        $this->accountService = new AccountService($accountRepository);
        $this->accountService->ensureSeeded(self::COMPANY);

        $journalRepository = new JournalRepository($this->database, new InMemoryTransaction());
        $ledger = new LedgerService($journalRepository, $accountRepository);

        $subscriptions = new SubscriptionService(new SubscriptionRepository($this->database));
        $subscriptions->extend(self::COMPANY, 30);

        $companySettings = new CompanySettingsRepository($this->database);

        $capture = new TransactionCaptureService($ledger, $this->accountService, $subscriptions, $companySettings);

        $this->templates = new RecurringTemplateRepository($this->database);
        $this->occurrences = new RecurringOccurrenceRepository($this->database);

        $company = new Company(
            self::COMPANY,
            self::CLIENT_ID,
            '2026/123456/07',
            'Test Company (Pty) Ltd',
            'Private Company',
            CompanyStatus::ACTIVE,
            new RegisteredAddress('1 Main Road', '', 'Sandton', 'Johannesburg', 'Gauteng', '2196')
        );
        $companies = new FakeCompanyService([self::COMPANY => $company]);

        $client = new Client(
            'client-uuid-1',
            self::WP_USER_ID,
            new Profile('Test', 'Client'),
            ClientStatus::ACTIVE,
            createdAt: new DateTimeImmutable(),
            id: self::CLIENT_ID
        );
        $clients = new FakeClientRepository([self::CLIENT_ID => $client]);

        $notificationQueue = new NotificationQueue($this->database);

        $this->recurring = new RecurringTransactionService(
            $this->templates,
            $this->occurrences,
            $capture,
            $companies,
            $clients,
            $notificationQueue
        );
    }

    private function rentCategory(): string
    {
        return $this->accountService->getByCode(self::COMPANY, ChartOfAccountsTemplate::CODE_RENT)->uuid;
    }

    private function queuedNotificationCount(): int
    {
        return count($this->database->all('bizhub_notification_queue'));
    }

    public function testCreateTemplateThenListReturnsIt(): void
    {
        $template = $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(2500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Monthly office rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );

        $found = $this->recurring->listTemplates(self::COMPANY);

        self::assertCount(1, $found);
        self::assertSame($template->uuid, $found[0]->uuid);
    }

    public function testGenerateDueOccurrencesCreatesAPendingOccurrenceAndAdvancesNextDueDate(): void
    {
        $template = $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(2500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Monthly office rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );

        $count = $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));

        self::assertSame(1, $count);

        $pending = $this->recurring->listPendingOccurrences(self::COMPANY);
        self::assertCount(1, $pending);
        self::assertSame('2026-08-01', $pending[0]->dueDate->format('Y-m-d'));

        $updatedTemplate = $this->templates->findByUuid($template->uuid);
        self::assertNotNull($updatedTemplate);
        self::assertSame('2026-09-01', $updatedTemplate->nextDueDate->format('Y-m-d'));
    }

    public function testGenerateDueOccurrencesRunTwiceTheSameDayNeverDuplicates(): void
    {
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(2500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Monthly office rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );

        $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));
        $second = $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));

        self::assertSame(0, $second);
        self::assertCount(1, $this->recurring->listPendingOccurrences(self::COMPANY));
    }

    public function testGenerateDueOccurrencesSkipsNotYetDueAndPausedTemplates(): void
    {
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Not yet due',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-12-01'),
        );

        $paused = $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Paused but due',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );
        $this->recurring->pauseTemplate(self::COMPANY, $paused->uuid);

        $count = $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));

        self::assertSame(0, $count);
    }

    public function testGenerateDueOccurrencesQueuesOneNotificationPerAffectedCompany(): void
    {
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(300.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Insurance',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );

        $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));

        // Two occurrences generated for the same company -> one notification, not two.
        self::assertSame(1, $this->queuedNotificationCount());
    }

    public function testConfirmOccurrencePostsABalancedExpenseEntry(): void
    {
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(2500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Monthly office rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );
        $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));

        $occurrence = $this->recurring->listPendingOccurrences(self::COMPANY)[0];

        $entry = $this->recurring->confirmOccurrence(self::COMPANY, $occurrence->uuid, actorId: 1);

        self::assertCount(2, $entry->lines);

        $stillPending = $this->recurring->listPendingOccurrences(self::COMPANY);
        self::assertCount(0, $stillPending);

        $resolved = $this->occurrences->findByUuid($occurrence->uuid);
        self::assertNotNull($resolved);
        self::assertSame($entry->uuid, $resolved->journalEntryUuid);
    }

    public function testConfirmOccurrenceAppliesAnAmountOverride(): void
    {
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(2500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Rent, escalated this month',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );
        $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));

        $occurrence = $this->recurring->listPendingOccurrences(self::COMPANY)[0];

        $entry = $this->recurring->confirmOccurrence(
            self::COMPANY,
            $occurrence->uuid,
            actorId: 1,
            overrideAmount: Money::fromRands(2750.00),
        );

        $rentLine = null;
        foreach ($entry->lines as $line) {
            if ($line->accountUuid === $this->rentCategory()) {
                $rentLine = $line;
            }
        }

        self::assertNotNull($rentLine);
        self::assertTrue($rentLine->debit->equals(Money::fromRands(2750.00)));
    }

    public function testSkipOccurrenceNeverPosts(): void
    {
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Cancelled service',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );
        $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));

        $occurrence = $this->recurring->listPendingOccurrences(self::COMPANY)[0];

        $this->recurring->skipOccurrence(self::COMPANY, $occurrence->uuid);

        self::assertCount(0, $this->recurring->listPendingOccurrences(self::COMPANY));

        $resolved = $this->occurrences->findByUuid($occurrence->uuid);
        self::assertNotNull($resolved);
        self::assertNull($resolved->journalEntryUuid);
    }

    public function testConfirmingAnAlreadyResolvedOccurrenceThrows(): void
    {
        $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );
        $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01'));
        $occurrence = $this->recurring->listPendingOccurrences(self::COMPANY)[0];

        $this->recurring->skipOccurrence(self::COMPANY, $occurrence->uuid);

        $this->expectException(ValidationException::class);
        $this->recurring->confirmOccurrence(self::COMPANY, $occurrence->uuid, actorId: 1);
    }

    public function testPauseThenResumeTemplateAffectsGeneration(): void
    {
        $template = $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );

        $this->recurring->pauseTemplate(self::COMPANY, $template->uuid);
        self::assertSame(0, $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01')));

        $this->recurring->resumeTemplate(self::COMPANY, $template->uuid);
        self::assertSame(1, $this->recurring->generateDueOccurrences(new DateTimeImmutable('2026-08-01')));
    }

    public function testDeleteTemplateRemovesItFromTheList(): void
    {
        $template = $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );

        $this->recurring->deleteTemplate(self::COMPANY, $template->uuid);

        self::assertCount(0, $this->recurring->listTemplates(self::COMPANY));
    }

    public function testAnotherCompanyCannotPauseResumeOrDeleteThisCompanysTemplate(): void
    {
        $template = $this->recurring->createTemplate(
            self::COMPANY,
            TransactionType::Expense,
            Money::fromRands(500.00),
            $this->rentCategory(),
            PaymentMethod::Bank,
            'Rent',
            false,
            RecurringFrequency::Monthly,
            new DateTimeImmutable('2026-08-01'),
        );

        $otherCompany = 'company-other';

        $this->expectException(ValidationException::class);
        $this->recurring->pauseTemplate($otherCompany, $template->uuid);
    }
}
