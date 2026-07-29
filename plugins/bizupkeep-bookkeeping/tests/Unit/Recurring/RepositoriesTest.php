<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Recurring;

use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\RecurringOccurrenceStatus;
use BizHub\Bookkeeping\Enums\TransactionType;
use BizHub\Bookkeeping\Entities\RecurringOccurrence;
use BizHub\Bookkeeping\Entities\RecurringTemplate;
use BizHub\Bookkeeping\Recurring\RecurringOccurrenceRepository;
use BizHub\Bookkeeping\Recurring\RecurringTemplateRepository;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
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

    private function makeTemplate(
        ?DateTimeImmutable $nextDueDate = null,
        bool $isActive = true,
        ?string $uuid = null
    ): RecurringTemplate {
        return new RecurringTemplate(
            uuid: $uuid ?? Uuid::generate(),
            companyUuid: self::COMPANY,
            transactionType: TransactionType::Expense,
            amount: Money::fromRands(2500.00),
            categoryAccountUuid: 'account-rent',
            paymentMethod: PaymentMethod::Bank,
            description: 'Monthly office rent',
            includesVat: false,
            frequency: RecurringFrequency::Monthly,
            nextDueDate: $nextDueDate ?? new DateTimeImmutable('2026-08-01'),
            isActive: $isActive,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function testSaveThenFindByUuidRoundTrips(): void
    {
        $repository = new RecurringTemplateRepository($this->database);
        $template = $this->makeTemplate();

        $repository->save($template);

        $found = $repository->findByUuid($template->uuid);

        self::assertNotNull($found);
        self::assertSame(self::COMPANY, $found->companyUuid);
        self::assertTrue($found->amount->equals(Money::fromRands(2500.00)));
        self::assertSame(RecurringFrequency::Monthly, $found->frequency);
        self::assertTrue($found->isActive);
    }

    public function testSavingAgainWithTheSameUuidUpdatesTheExistingRow(): void
    {
        $repository = new RecurringTemplateRepository($this->database);
        $uuid = Uuid::generate();

        $repository->save($this->makeTemplate(uuid: $uuid));
        $repository->save($this->makeTemplate(nextDueDate: new DateTimeImmutable('2026-09-01'), uuid: $uuid));

        $found = $repository->findByUuid($uuid);

        self::assertNotNull($found);
        self::assertSame('2026-09-01', $found->nextDueDate->format('Y-m-d'));
    }

    public function testFindByCompanyUuidReturnsOnlyThatCompanysTemplates(): void
    {
        $repository = new RecurringTemplateRepository($this->database);

        $repository->save($this->makeTemplate());
        $repository->save(new RecurringTemplate(
            uuid: Uuid::generate(),
            companyUuid: 'company-2',
            transactionType: TransactionType::Income,
            amount: Money::fromRands(1000.00),
            categoryAccountUuid: 'account-sales',
            paymentMethod: PaymentMethod::Bank,
            description: 'Other company template',
            includesVat: false,
            frequency: RecurringFrequency::Weekly,
            nextDueDate: new DateTimeImmutable('2026-08-01'),
            isActive: true,
            createdAt: new DateTimeImmutable(),
        ));

        self::assertCount(1, $repository->findByCompanyUuid(self::COMPANY));
    }

    public function testFindDueExcludesInactiveAndNotYetDueTemplates(): void
    {
        $repository = new RecurringTemplateRepository($this->database);

        $due = $this->makeTemplate(nextDueDate: new DateTimeImmutable('2026-08-01'));
        $notYetDue = $this->makeTemplate(nextDueDate: new DateTimeImmutable('2026-12-01'));
        $inactiveButDue = $this->makeTemplate(nextDueDate: new DateTimeImmutable('2026-08-01'), isActive: false);

        $repository->save($due);
        $repository->save($notYetDue);
        $repository->save($inactiveButDue);

        $results = $repository->findDue(new DateTimeImmutable('2026-08-15'));

        self::assertCount(1, $results);
        self::assertSame($due->uuid, $results[0]->uuid);
    }

    public function testDeleteRemovesTheTemplate(): void
    {
        $repository = new RecurringTemplateRepository($this->database);
        $template = $this->makeTemplate();
        $repository->save($template);

        $repository->delete($template->uuid);

        self::assertNull($repository->findByUuid($template->uuid));
    }

    private function makeOccurrence(
        string $templateUuid,
        RecurringOccurrenceStatus $status = RecurringOccurrenceStatus::Pending,
        ?DateTimeImmutable $dueDate = null
    ): RecurringOccurrence {
        return new RecurringOccurrence(
            uuid: Uuid::generate(),
            templateUuid: $templateUuid,
            companyUuid: self::COMPANY,
            dueDate: $dueDate ?? new DateTimeImmutable('2026-08-01'),
            status: $status,
            journalEntryUuid: null,
            generatedAt: new DateTimeImmutable(),
        );
    }

    public function testInsertThenFindByUuidRoundTrips(): void
    {
        $repository = new RecurringOccurrenceRepository($this->database);
        $occurrence = $this->makeOccurrence('template-1');

        $repository->insert($occurrence);

        $found = $repository->findByUuid($occurrence->uuid);

        self::assertNotNull($found);
        self::assertSame(RecurringOccurrenceStatus::Pending, $found->status);
        self::assertNull($found->journalEntryUuid);
    }

    public function testExistsForTemplateAndDateDetectsAnAlreadyGeneratedOccurrence(): void
    {
        $repository = new RecurringOccurrenceRepository($this->database);
        $repository->insert($this->makeOccurrence('template-1', dueDate: new DateTimeImmutable('2026-08-01')));

        self::assertTrue($repository->existsForTemplateAndDate('template-1', new DateTimeImmutable('2026-08-01')));
        self::assertFalse($repository->existsForTemplateAndDate('template-1', new DateTimeImmutable('2026-09-01')));
        self::assertFalse($repository->existsForTemplateAndDate('template-2', new DateTimeImmutable('2026-08-01')));
    }

    public function testFindByCompanyUuidFiltersByStatus(): void
    {
        $repository = new RecurringOccurrenceRepository($this->database);

        $repository->insert($this->makeOccurrence('template-1', RecurringOccurrenceStatus::Pending));
        $repository->insert($this->makeOccurrence('template-1', RecurringOccurrenceStatus::Posted, new DateTimeImmutable('2026-09-01')));

        $pending = $repository->findByCompanyUuid(self::COMPANY, RecurringOccurrenceStatus::Pending);

        self::assertCount(1, $pending);
        self::assertSame(RecurringOccurrenceStatus::Pending, $pending[0]->status);
    }

    public function testSaveUpdatesAnExistingOccurrence(): void
    {
        $repository = new RecurringOccurrenceRepository($this->database);
        $occurrence = $this->makeOccurrence('template-1');
        $repository->insert($occurrence);

        $posted = new RecurringOccurrence(
            uuid: $occurrence->uuid,
            templateUuid: $occurrence->templateUuid,
            companyUuid: $occurrence->companyUuid,
            dueDate: $occurrence->dueDate,
            status: RecurringOccurrenceStatus::Posted,
            journalEntryUuid: 'entry-1',
            generatedAt: $occurrence->generatedAt,
            resolvedAt: new DateTimeImmutable(),
        );

        $repository->save($posted);

        $found = $repository->findByUuid($occurrence->uuid);
        self::assertNotNull($found);
        self::assertSame(RecurringOccurrenceStatus::Posted, $found->status);
        self::assertSame('entry-1', $found->journalEntryUuid);
    }
}
