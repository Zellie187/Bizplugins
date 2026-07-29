<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\BankImport;

use BizHub\Bookkeeping\Contracts\StagedTransactionRepositoryInterface;
use BizHub\Bookkeeping\Entities\StagedTransaction;
use BizHub\Bookkeeping\Enums\StagedTransactionStatus;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the staged
 * transactions table.
 *
 * @package BizHub\Bookkeeping\BankImport
 */
final class StagedTransactionRepository implements StagedTransactionRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_staged_transactions';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid, ?StagedTransactionStatus $status = null): array
    {
        $criteria = ['company_uuid' => $companyUuid];

        if ($status !== null) {
            $criteria['status'] = $status->value;
        }

        $rows = $this->database->findAll(self::TABLE, $criteria, ['transaction_date' => 'ASC']);

        return array_map($this->hydrate(...), $rows);
    }

    public function findByUuid(string $uuid): ?StagedTransaction
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function existsByRowHash(string $companyUuid, string $rowHash): bool
    {
        return $this->database->exists(self::TABLE, ['company_uuid' => $companyUuid, 'row_hash' => $rowHash]);
    }

    public function insertMany(array $transactions): int
    {
        $inserted = 0;

        foreach ($transactions as $transaction) {
            if ($this->existsByRowHash($transaction->companyUuid, $transaction->rowHash)) {
                continue;
            }

            $this->database->insert(self::TABLE, $this->dehydrate($transaction));
            $inserted++;
        }

        return $inserted;
    }

    public function save(StagedTransaction $transaction): StagedTransaction
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $transaction->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($transaction), ['uuid' => $transaction->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($transaction));
        }

        return $transaction;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(StagedTransaction $transaction): array
    {
        return [
            'uuid' => $transaction->uuid,
            'company_uuid' => $transaction->companyUuid,
            'source_account_uuid' => $transaction->sourceAccountUuid,
            'transaction_date' => $transaction->transactionDate->format('Y-m-d'),
            'description' => $transaction->description,
            'amount_minor' => $transaction->amount->minorUnits(),
            'row_hash' => $transaction->rowHash,
            'status' => $transaction->status->value,
            'category_account_uuid' => $transaction->categoryAccountUuid,
            'journal_entry_uuid' => $transaction->journalEntryUuid,
            'imported_at' => $transaction->importedAt->format('Y-m-d H:i:s'),
            'categorized_at' => $transaction->categorizedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): StagedTransaction
    {
        return new StagedTransaction(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            sourceAccountUuid: (string) $row['source_account_uuid'],
            transactionDate: new DateTimeImmutable((string) $row['transaction_date']),
            description: (string) $row['description'],
            amount: Money::fromMinorUnits((int) $row['amount_minor']),
            rowHash: (string) $row['row_hash'],
            status: StagedTransactionStatus::from((string) $row['status']),
            categoryAccountUuid: $this->nullableString($row['category_account_uuid'] ?? null),
            journalEntryUuid: $this->nullableString($row['journal_entry_uuid'] ?? null),
            importedAt: new DateTimeImmutable((string) $row['imported_at']),
            categorizedAt: isset($row['categorized_at']) && $row['categorized_at'] !== null
                ? new DateTimeImmutable((string) $row['categorized_at'])
                : null,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
