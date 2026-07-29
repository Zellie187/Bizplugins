<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\RecurringTemplate;
use DateTimeImmutable;

/**
 * Persistence contract for recurring transaction templates. The only
 * class allowed to touch DatabaseInterface for this table.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface RecurringTemplateRepositoryInterface
{
    /**
     * @return RecurringTemplate[]
     */
    public function findByCompanyUuid(string $companyUuid): array;

    public function findByUuid(string $uuid): ?RecurringTemplate;

    /**
     * Every active template whose next_due_date has arrived, across all
     * companies - the cron scan's entry point.
     *
     * @return RecurringTemplate[]
     */
    public function findDue(DateTimeImmutable $asOf): array;

    public function save(RecurringTemplate $template): RecurringTemplate;

    public function delete(string $uuid): void;
}
