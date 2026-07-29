<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\BankImport;

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Contracts\BankImportServiceInterface;
use BizHub\Bookkeeping\Contracts\ImportMappingRepositoryInterface;
use BizHub\Bookkeeping\Contracts\StagedTransactionRepositoryInterface;
use BizHub\Bookkeeping\Contracts\TransactionCaptureServiceInterface;
use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\DTO\ImportResult;
use BizHub\Bookkeeping\Entities\ImportMapping;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Entities\StagedTransaction;
use BizHub\Bookkeeping\Enums\ImportAmountStyle;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\StagedTransactionStatus;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Bookkeeping\Export\CsvReader;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use Throwable;

/**
 * Public API for bank-statement CSV import. Never posts to the ledger
 * itself - categorize() always delegates to
 * TransactionCaptureServiceInterface, the same code path manual
 * capture uses, so this module inherits the subscription gate and the
 * balanced-entry guarantee for free.
 *
 * @package BizHub\Bookkeeping\BankImport
 */
final class BankImportService implements BankImportServiceInterface
{
    public function __construct(
        private readonly ImportMappingRepositoryInterface $mappings,
        private readonly StagedTransactionRepositoryInterface $staged,
        private readonly AccountServiceInterface $accounts,
        private readonly TransactionCaptureServiceInterface $capture,
        private readonly CsvReader $csvReader
    ) {
    }

    public function getMapping(string $companyUuid): ?ImportMapping
    {
        return $this->mappings->findByCompanyUuid($companyUuid);
    }

    public function saveMapping(
        string $companyUuid,
        string $dateColumn,
        string $descriptionColumn,
        ImportAmountStyle $amountStyle,
        ?string $amountColumn,
        ?string $debitColumn,
        ?string $creditColumn,
        string $dateFormat
    ): ImportMapping {
        $existing = $this->mappings->findByCompanyUuid($companyUuid);
        $now = new DateTimeImmutable();

        return $this->mappings->save(new ImportMapping(
            uuid: $existing?->uuid ?? Uuid::generate(),
            companyUuid: $companyUuid,
            dateColumn: $dateColumn,
            descriptionColumn: $descriptionColumn,
            amountStyle: $amountStyle,
            amountColumn: $amountColumn,
            debitColumn: $debitColumn,
            creditColumn: $creditColumn,
            dateFormat: $dateFormat,
            createdAt: $existing?->createdAt ?? $now,
            updatedAt: $now,
        ));
    }

    public function previewRows(string $csvContent, ImportMapping $mapping): array
    {
        $preview = [];

        foreach (array_slice($this->csvReader->readRows($csvContent), 0, 5) as $row) {
            $parsed = $this->parseRow($row, $mapping);

            if ($parsed === null) {
                continue;
            }

            [$date, $description, $amount] = $parsed;

            $preview[] = [
                'date' => $date->format('Y-m-d'),
                'description' => $description,
                'amount' => $amount->format(),
            ];
        }

        return $preview;
    }

    public function import(
        string $companyUuid,
        string $sourceAccountUuid,
        string $csvContent,
        ImportMapping $mapping
    ): ImportResult {
        $now = new DateTimeImmutable();
        $toInsert = [];
        $unparseable = 0;

        foreach ($this->csvReader->readRows($csvContent) as $row) {
            $parsed = $this->parseRow($row, $mapping);

            if ($parsed === null) {
                $unparseable++;

                continue;
            }

            [$date, $description, $amount] = $parsed;

            $toInsert[] = new StagedTransaction(
                uuid: Uuid::generate(),
                companyUuid: $companyUuid,
                sourceAccountUuid: $sourceAccountUuid,
                transactionDate: $date,
                description: $description,
                amount: $amount,
                rowHash: $this->rowHash($companyUuid, $date, $description, $amount),
                status: StagedTransactionStatus::Pending,
                categoryAccountUuid: null,
                journalEntryUuid: null,
                importedAt: $now,
            );
        }

        $attempted = count($toInsert);
        $insertedCount = $this->staged->insertMany($toInsert);

        return new ImportResult($insertedCount, $attempted - $insertedCount, $unparseable);
    }

    public function listPending(string $companyUuid): array
    {
        return $this->staged->findByCompanyUuid($companyUuid, StagedTransactionStatus::Pending);
    }

    public function categorize(
        string $companyUuid,
        string $stagedTransactionUuid,
        string $categoryAccountUuid,
        int $actorId
    ): JournalEntry {
        $staged = $this->ownedPendingStagedTransaction($companyUuid, $stagedTransactionUuid);

        $data = new CaptureTransactionData(
            date: $staged->transactionDate,
            amount: Money::fromMinorUnits(abs($staged->amount->minorUnits())),
            categoryAccountUuid: $categoryAccountUuid,
            paymentMethod: $this->resolvePaymentMethod($staged->sourceAccountUuid),
            description: $staged->description,
        );

        $entry = $staged->isIncomeShaped()
            ? $this->capture->captureIncome($companyUuid, $data, $actorId)
            : $this->capture->captureExpense($companyUuid, $data, $actorId);

        $this->staged->save(new StagedTransaction(
            uuid: $staged->uuid,
            companyUuid: $staged->companyUuid,
            sourceAccountUuid: $staged->sourceAccountUuid,
            transactionDate: $staged->transactionDate,
            description: $staged->description,
            amount: $staged->amount,
            rowHash: $staged->rowHash,
            status: StagedTransactionStatus::Categorized,
            categoryAccountUuid: $categoryAccountUuid,
            journalEntryUuid: $entry->uuid,
            importedAt: $staged->importedAt,
            categorizedAt: new DateTimeImmutable(),
        ));

        return $entry;
    }

    public function bulkCategorize(
        string $companyUuid,
        array $stagedTransactionUuids,
        string $categoryAccountUuid,
        int $actorId
    ): int {
        $count = 0;

        foreach ($stagedTransactionUuids as $uuid) {
            try {
                $this->categorize($companyUuid, $uuid, $categoryAccountUuid, $actorId);
                $count++;
            } catch (Throwable) {
                // One row's failure (already categorized elsewhere, an
                // inactive subscription, etc.) must never lose the rest
                // of the batch.
                continue;
            }
        }

        return $count;
    }

    public function ignore(string $companyUuid, string $stagedTransactionUuid): void
    {
        $staged = $this->ownedPendingStagedTransaction($companyUuid, $stagedTransactionUuid);

        $this->staged->save(new StagedTransaction(
            uuid: $staged->uuid,
            companyUuid: $staged->companyUuid,
            sourceAccountUuid: $staged->sourceAccountUuid,
            transactionDate: $staged->transactionDate,
            description: $staged->description,
            amount: $staged->amount,
            rowHash: $staged->rowHash,
            status: StagedTransactionStatus::Ignored,
            categoryAccountUuid: null,
            journalEntryUuid: null,
            importedAt: $staged->importedAt,
            categorizedAt: new DateTimeImmutable(),
        ));
    }

    private function ownedPendingStagedTransaction(
        string $companyUuid,
        string $stagedTransactionUuid
    ): StagedTransaction {
        $staged = $this->staged->findByUuid($stagedTransactionUuid);

        if ($staged === null || $staged->companyUuid !== $companyUuid) {
            throw new ValidationException(sprintf(
                'No staged transaction "%s" found for this company.',
                $stagedTransactionUuid
            ));
        }

        if ($staged->status !== StagedTransactionStatus::Pending) {
            throw new ValidationException('This staged transaction has already been categorized or ignored.');
        }

        return $staged;
    }

    private function resolvePaymentMethod(string $accountUuid): PaymentMethod
    {
        $account = $this->accounts->getAccount($accountUuid);

        return match ($account->code) {
            ChartOfAccountsTemplate::CODE_BANK_ACCOUNT => PaymentMethod::Bank,
            ChartOfAccountsTemplate::CODE_CASH_ON_HAND => PaymentMethod::Cash,
            default => throw new ValidationException(sprintf(
                'Account "%s" is not a valid bank-import source account.',
                $accountUuid
            )),
        };
    }

    private function rowHash(string $companyUuid, DateTimeImmutable $date, string $description, Money $amount): string
    {
        return hash(
            'sha256',
            $companyUuid . '|' . $date->format('Y-m-d') . '|' . $description . '|' . $amount->minorUnits()
        );
    }

    /**
     * @param array<string,string> $row
     *
     * @return array{0:DateTimeImmutable,1:string,2:Money}|null
     */
    private function parseRow(array $row, ImportMapping $mapping): ?array
    {
        $dateRaw = trim($row[$mapping->dateColumn] ?? '');
        $description = trim($row[$mapping->descriptionColumn] ?? '');

        if ($dateRaw === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat($mapping->dateFormat, $dateRaw);

        if ($date === false) {
            return null;
        }

        $amount = $this->parseAmount($row, $mapping);

        if ($amount === null || $amount->isZero()) {
            return null;
        }

        return [$date->setTime(0, 0), $description, $amount];
    }

    /**
     * @param array<string,string> $row
     */
    private function parseAmount(array $row, ImportMapping $mapping): ?Money
    {
        if ($mapping->amountStyle === ImportAmountStyle::Signed) {
            $raw = str_replace(',', '', trim($row[$mapping->amountColumn ?? ''] ?? ''));

            return $raw !== '' && is_numeric($raw) ? Money::fromRands((float) $raw) : null;
        }

        $debitRaw = str_replace(',', '', trim($row[$mapping->debitColumn ?? ''] ?? ''));
        $creditRaw = str_replace(',', '', trim($row[$mapping->creditColumn ?? ''] ?? ''));

        $debit = $debitRaw !== '' && is_numeric($debitRaw) ? (float) $debitRaw : 0.0;
        $credit = $creditRaw !== '' && is_numeric($creditRaw) ? (float) $creditRaw : 0.0;

        if ($debit === 0.0 && $credit === 0.0) {
            return null;
        }

        return Money::fromRands($credit - $debit);
    }
}
