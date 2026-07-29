<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\StagedTransaction;
use BizHub\Bookkeeping\Enums\StagedTransactionStatus;

/**
 * Persistence contract for imported bank statement rows. The only
 * class allowed to touch DatabaseInterface for this table.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface StagedTransactionRepositoryInterface
{
    /**
     * @return StagedTransaction[]
     */
    public function findByCompanyUuid(string $companyUuid, ?StagedTransactionStatus $status = null): array;

    public function findByUuid(string $uuid): ?StagedTransaction;

    public function existsByRowHash(string $companyUuid, string $rowHash): bool;

    /**
     * Insert every row that isn't already a duplicate by (company_uuid,
     * row_hash) - a row whose hash already exists for this company is
     * silently skipped, not an error, since re-uploading an overlapping
     * statement is the expected/normal case this dedup exists for.
     *
     * @param StagedTransaction[] $transactions
     *
     * @return int Number actually inserted (excludes skipped duplicates).
     */
    public function insertMany(array $transactions): int;

    public function save(StagedTransaction $transaction): StagedTransaction;
}
