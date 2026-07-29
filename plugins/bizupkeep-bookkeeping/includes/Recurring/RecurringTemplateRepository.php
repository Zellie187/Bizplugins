<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Recurring;

use BizHub\Bookkeeping\Contracts\RecurringTemplateRepositoryInterface;
use BizHub\Bookkeeping\Entities\RecurringTemplate;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\TransactionType;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the recurring templates
 * table.
 *
 * findDue() fetches every active template via an equality-only
 * findAll() and filters by next_due_date in PHP - the same
 * DatabaseInterface::findAll()-is-equality-only, filter-in-PHP pattern
 * JournalRepository already established for date-range queries, which
 * keeps this class fully testable against InMemoryDatabase.
 *
 * @package BizHub\Bookkeeping\Recurring
 */
final class RecurringTemplateRepository implements RecurringTemplateRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_recurring_templates';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid): array
    {
        $rows = $this->database->findAll(self::TABLE, ['company_uuid' => $companyUuid], ['next_due_date' => 'ASC']);

        return array_map($this->hydrate(...), $rows);
    }

    public function findByUuid(string $uuid): ?RecurringTemplate
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findDue(DateTimeImmutable $asOf): array
    {
        $rows = $this->database->findAll(self::TABLE, ['is_active' => 1]);

        $due = [];

        foreach ($rows as $row) {
            if (new DateTimeImmutable((string) $row['next_due_date']) <= $asOf) {
                $due[] = $this->hydrate($row);
            }
        }

        return $due;
    }

    public function save(RecurringTemplate $template): RecurringTemplate
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $template->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($template), ['uuid' => $template->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($template));
        }

        return $template;
    }

    public function delete(string $uuid): void
    {
        $this->database->delete(self::TABLE, ['uuid' => $uuid]);
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(RecurringTemplate $template): array
    {
        return [
            'uuid' => $template->uuid,
            'company_uuid' => $template->companyUuid,
            'transaction_type' => $template->transactionType->value,
            'amount_minor' => $template->amount->minorUnits(),
            'category_account_uuid' => $template->categoryAccountUuid,
            'payment_method' => $template->paymentMethod->value,
            'description' => $template->description,
            'includes_vat' => $template->includesVat ? 1 : 0,
            'frequency' => $template->frequency->value,
            'next_due_date' => $template->nextDueDate->format('Y-m-d'),
            'is_active' => $template->isActive ? 1 : 0,
            'created_at' => $template->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $template->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): RecurringTemplate
    {
        return new RecurringTemplate(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            transactionType: TransactionType::from((string) $row['transaction_type']),
            amount: Money::fromMinorUnits((int) $row['amount_minor']),
            categoryAccountUuid: (string) $row['category_account_uuid'],
            paymentMethod: PaymentMethod::from((string) $row['payment_method']),
            description: (string) $row['description'],
            includesVat: (bool) (int) $row['includes_vat'],
            frequency: RecurringFrequency::from((string) $row['frequency']),
            nextDueDate: new DateTimeImmutable((string) $row['next_due_date']),
            isActive: (bool) (int) $row['is_active'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
        );
    }
}
