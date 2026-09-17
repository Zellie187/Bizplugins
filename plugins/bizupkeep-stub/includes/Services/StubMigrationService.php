<?php

declare(strict_types=1);

namespace BizHub\Stub\Services;

use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Enums\AccountType;
use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Stub\Contracts\StubApiClientInterface;
use BizHub\Stub\Contracts\StubBusinessProvisionerInterface;
use BizHub\Stub\Contracts\StubBusinessRepositoryInterface;
use BizHub\Stub\Contracts\StubMigrationServiceInterface;
use BizHub\Stub\Exceptions\StubException;
use DateTimeImmutable;

/**
 * One-time historical backfill: every Income/Expense-classified
 * JournalLine a Company has ever posted, pushed into its Stub business
 * via POST /api/push/many (simpler than the settlement-file/S3 flow -
 * no presigned URL or webhook round trip needed for a single push,
 * and push/many's schema is confirmed directly against the live
 * OpenAPI spec, unlike settlement-file's less-documented shape).
 *
 * Asset/Liability/Equity-only lines (e.g. the offsetting Bank/Cash leg
 * of every entry, an owner's capital injection, a loan drawdown) are
 * deliberately skipped - Stub's API has no generic ledger/balance-
 * sheet concept, only income and expenses, so this is a lossy but
 * intentional transformation. accountid is deliberately omitted from
 * the pushed rows (BizUpKeep's internal account UUIDs mean nothing in
 * Stub's own chart of accounts); the BizUpKeep account name is folded
 * into "name" instead so the categorisation isn't lost entirely.
 *
 * @package BizHub\Stub\Services
 */
final class StubMigrationService implements StubMigrationServiceInterface
{
    public function __construct(
        private readonly StubBusinessRepositoryInterface $businesses,
        private readonly StubBusinessProvisionerInterface $provisioner,
        private readonly StubApiClientInterface $api,
        private readonly CompanyServiceInterface $companies,
        private readonly ClientRepositoryInterface $clients,
        private readonly JournalRepositoryInterface $journal,
        private readonly AccountRepositoryInterface $accounts
    ) {
    }

    public function migrateCompany(string $companyUuid): void
    {
        $business = $this->businesses->findByCompanyUuid($companyUuid);

        if ($business === null) {
            // Provision on the fly if staff runs a migration before
            // the company's first embed/dashboard view has already
            // done so.
            $company = $this->companies->getCompany($companyUuid);
            $client = $this->clients->find($company->getClientId());

            if ($client === null) {
                throw new StubException(sprintf('Company %s has no owning client - cannot provision a Stub business.', $companyUuid));
            }

            $this->provisioner->findOrCreate($client, $company);
            $business = $this->businesses->findByCompanyUuid($companyUuid);
        }

        if ($business === null) {
            throw new StubException(sprintf('Could not provision a Stub business for company %s.', $companyUuid));
        }

        $accountNames = [];
        $accountTypes = [];

        foreach ($this->accounts->findAllForCompany($companyUuid, null, onlyActive: false) as $account) {
            $accountNames[$account->uuid] = $account->name;
            $accountTypes[$account->uuid] = $account->type;
        }

        $entries = $this->journal->findEntriesForCompany($companyUuid, DateRange::sinceInception(new DateTimeImmutable()));

        $income = [];
        $expenses = [];

        foreach ($entries as $entry) {
            foreach ($entry->lines as $index => $line) {
                $type = $accountTypes[$line->accountUuid] ?? null;

                if (! in_array($type, [AccountType::Income, AccountType::Expense], true)) {
                    continue;
                }

                $row = [
                    'id' => $entry->uuid . '-' . $index,
                    'date' => $entry->entryDate->format('Y-m-d'),
                    'name' => $accountNames[$line->accountUuid] ?? $entry->description,
                    'notes' => $line->memo !== '' ? $line->memo : $entry->description,
                    'currency' => 'ZAR',
                    'amount' => $line->amount()->toRands(),
                ];

                if (AccountType::Income === $type) {
                    $income[] = $row;
                } else {
                    $expenses[] = $row;
                }
            }
        }

        try {
            // 500 rows per call is a self-imposed, conservative batch
            // size - push/many's own limit is not documented (unlike
            // the OAuth data-sync endpoints' confirmed 5,000-row cap),
            // so this errs small rather than risk an undocumented
            // rejection on a large historical backfill.
            foreach (array_chunk($income, 500) as $chunk) {
                $this->api->pushMany($business->stubBusinessUid, $chunk, []);
            }

            foreach (array_chunk($expenses, 500) as $chunk) {
                $this->api->pushMany($business->stubBusinessUid, [], $chunk);
            }
        } catch (StubException $e) {
            $this->businesses->save($business->withMigrationFailed($e->getMessage()));

            throw $e;
        }

        $this->businesses->save($business->withMigrated(new DateTimeImmutable()));
    }
}
