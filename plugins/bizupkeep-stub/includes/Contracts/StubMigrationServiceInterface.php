<?php

declare(strict_types=1);

namespace BizHub\Stub\Contracts;

/**
 * One-time, staff-triggered migration of a Company's historical
 * Income/Expense-classified journal lines into its Stub business.
 * Idempotent per call (records migratedAt on success so the dashboard
 * can show it as done), but re-running it after a partial failure will
 * re-submit everything again - Stub's own idempotent-by-externalid
 * behaviour (documented for the OAuth data-sync endpoints) is not
 * confirmed for push/many, so avoid re-running a successful migration.
 *
 * @package BizHub\Stub\Contracts
 */
interface StubMigrationServiceInterface
{
    /**
     * @throws \BizHub\Stub\Exceptions\StubException If the company has
     *                                                no Stub business yet, or the push itself fails.
     */
    public function migrateCompany(string $companyUuid): void;
}
