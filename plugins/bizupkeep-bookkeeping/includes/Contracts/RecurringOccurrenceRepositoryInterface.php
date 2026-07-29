<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\RecurringOccurrence;
use BizHub\Bookkeeping\Enums\RecurringOccurrenceStatus;
use DateTimeImmutable;

/**
 * Persistence contract for cron-generated recurring occurrences. The
 * only class allowed to touch DatabaseInterface for this table.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface RecurringOccurrenceRepositoryInterface
{
    /**
     * @return RecurringOccurrence[]
     */
    public function findByCompanyUuid(string $companyUuid, ?RecurringOccurrenceStatus $status = null): array;

    public function findByUuid(string $uuid): ?RecurringOccurrence;

    public function existsForTemplateAndDate(string $templateUuid, DateTimeImmutable $dueDate): bool;

    public function insert(RecurringOccurrence $occurrence): RecurringOccurrence;

    public function save(RecurringOccurrence $occurrence): RecurringOccurrence;
}
