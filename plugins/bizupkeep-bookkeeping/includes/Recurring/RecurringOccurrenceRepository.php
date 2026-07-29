<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Recurring;

use BizHub\Bookkeeping\Contracts\RecurringOccurrenceRepositoryInterface;
use BizHub\Bookkeeping\Entities\RecurringOccurrence;
use BizHub\Bookkeeping\Enums\RecurringOccurrenceStatus;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the recurring
 * occurrences table.
 *
 * @package BizHub\Bookkeeping\Recurring
 */
final class RecurringOccurrenceRepository implements RecurringOccurrenceRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_recurring_occurrences';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid, ?RecurringOccurrenceStatus $status = null): array
    {
        $criteria = ['company_uuid' => $companyUuid];

        if ($status !== null) {
            $criteria['status'] = $status->value;
        }

        $rows = $this->database->findAll(self::TABLE, $criteria, ['due_date' => 'ASC']);

        return array_map($this->hydrate(...), $rows);
    }

    public function findByUuid(string $uuid): ?RecurringOccurrence
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function existsForTemplateAndDate(string $templateUuid, DateTimeImmutable $dueDate): bool
    {
        return $this->database->exists(self::TABLE, [
            'template_uuid' => $templateUuid,
            'due_date' => $dueDate->format('Y-m-d'),
        ]);
    }

    public function insert(RecurringOccurrence $occurrence): RecurringOccurrence
    {
        $this->database->insert(self::TABLE, $this->dehydrate($occurrence));

        return $occurrence;
    }

    public function save(RecurringOccurrence $occurrence): RecurringOccurrence
    {
        $this->database->update(self::TABLE, $this->dehydrate($occurrence), ['uuid' => $occurrence->uuid]);

        return $occurrence;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(RecurringOccurrence $occurrence): array
    {
        return [
            'uuid' => $occurrence->uuid,
            'template_uuid' => $occurrence->templateUuid,
            'company_uuid' => $occurrence->companyUuid,
            'due_date' => $occurrence->dueDate->format('Y-m-d'),
            'status' => $occurrence->status->value,
            'journal_entry_uuid' => $occurrence->journalEntryUuid,
            'generated_at' => $occurrence->generatedAt->format('Y-m-d H:i:s'),
            'resolved_at' => $occurrence->resolvedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): RecurringOccurrence
    {
        return new RecurringOccurrence(
            uuid: (string) $row['uuid'],
            templateUuid: (string) $row['template_uuid'],
            companyUuid: (string) $row['company_uuid'],
            dueDate: new DateTimeImmutable((string) $row['due_date']),
            status: RecurringOccurrenceStatus::from((string) $row['status']),
            journalEntryUuid: isset($row['journal_entry_uuid']) && $row['journal_entry_uuid'] !== null
                ? (string) $row['journal_entry_uuid']
                : null,
            generatedAt: new DateTimeImmutable((string) $row['generated_at']),
            resolvedAt: isset($row['resolved_at']) && $row['resolved_at'] !== null
                ? new DateTimeImmutable((string) $row['resolved_at'])
                : null,
        );
    }
}
