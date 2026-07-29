<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\DTO\ImportResult;
use BizHub\Bookkeeping\Entities\ImportMapping;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Entities\StagedTransaction;
use BizHub\Bookkeeping\Enums\ImportAmountStyle;

/**
 * Public API for bank-statement CSV import: map columns, preview,
 * stage (deduped), then review and categorize each staged row into a
 * real journal entry. Categorizing always goes through
 * TransactionCaptureServiceInterface - this module never posts to the
 * ledger itself, it only ever builds the same input that manual
 * capture already builds.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface BankImportServiceInterface
{
    public function getMapping(string $companyUuid): ?ImportMapping;

    public function saveMapping(
        string $companyUuid,
        string $dateColumn,
        string $descriptionColumn,
        ImportAmountStyle $amountStyle,
        ?string $amountColumn,
        ?string $debitColumn,
        ?string $creditColumn,
        string $dateFormat
    ): ImportMapping;

    /**
     * Parse a CSV against a mapping without persisting anything - for
     * the confirm-before-import preview step.
     *
     * @return array<int,array{date:string,description:string,amount:string}>
     */
    public function previewRows(string $csvContent, ImportMapping $mapping): array;

    /**
     * Parse and stage every row, skipping ones that duplicate an
     * already-staged row for this company (same date+description+amount
     * hash) and ones that fail to parse - neither ever aborts the batch.
     */
    public function import(
        string $companyUuid,
        string $sourceAccountUuid,
        string $csvContent,
        ImportMapping $mapping
    ): ImportResult;

    /**
     * @return StagedTransaction[]
     */
    public function listPending(string $companyUuid): array;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function categorize(
        string $companyUuid,
        string $stagedTransactionUuid,
        string $categoryAccountUuid,
        int $actorId
    ): JournalEntry;

    /**
     * Apply one category to many staged rows at once - the real
     * efficiency win, since many statement lines share a category.
     * One row's failure doesn't prevent the rest from being categorized.
     *
     * @param string[] $stagedTransactionUuids
     *
     * @return int Number successfully categorized.
     */
    public function bulkCategorize(
        string $companyUuid,
        array $stagedTransactionUuids,
        string $categoryAccountUuid,
        int $actorId
    ): int;

    /**
     * Mark a staged row ignored without ever posting it - internal
     * transfers, non-business lines.
     */
    public function ignore(string $companyUuid, string $stagedTransactionUuid): void;
}
