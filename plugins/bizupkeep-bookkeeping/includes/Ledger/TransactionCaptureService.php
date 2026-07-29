<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Ledger;

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Contracts\LedgerServiceInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\Contracts\TransactionCaptureServiceInterface;
use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\DTO\JournalLineData;
use BizHub\Bookkeeping\DTO\ManualJournalEntryData;
use BizHub\Bookkeeping\Entities\JournalEntry;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Exceptions\SubscriptionInactiveException;
use BizHub\Bookkeeping\Exceptions\ValidationException;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Bookkeeping\Vat\VatCalculator;

/**
 * Translates the client-facing "capture income/expense" form input into
 * a correctly-balanced journal entry - 2 lines normally, or 3 when
 * $data->includesVat splits out a VAT Input/Output line - resolving
 * the Bank/Cash offsetting account from the chosen payment method
 * automatically.
 *
 * Gated on an active bookkeeping subscription - this is the paid
 * action a client's monthly subscription unlocks. LedgerService itself
 * is NOT gated, which is what keeps staff's manual journal entries
 * (posted via ManualJournalEntryPage, which calls LedgerService
 * directly) unaffected by a client's subscription status.
 *
 * @package BizHub\Bookkeeping\Ledger
 */
final class TransactionCaptureService implements TransactionCaptureServiceInterface
{
    public function __construct(
        private readonly LedgerServiceInterface $ledger,
        private readonly AccountServiceInterface $accounts,
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly CompanySettingsRepositoryInterface $companySettings
    ) {
    }

    public function captureIncome(string $companyUuid, CaptureTransactionData $data, int $actorId): JournalEntry
    {
        $this->assertActiveSubscription($companyUuid);
        $this->assertPositiveAmount($data);

        $offsetAccount = $this->accounts->getByCode($companyUuid, $data->paymentMethod->accountCode());
        $vat = $this->resolveVatPortion($companyUuid, $data);

        if ($vat->isPositive()) {
            $vatAccount = $this->accounts->getByCode($companyUuid, ChartOfAccountsTemplate::CODE_VAT_OUTPUT);
            $net = $data->amount->subtract($vat);

            $lines = [
                JournalLineData::debit($offsetAccount->uuid, $data->amount),
                JournalLineData::credit($data->categoryAccountUuid, $net),
                JournalLineData::credit($vatAccount->uuid, $vat),
            ];
        } else {
            $lines = [
                JournalLineData::debit($offsetAccount->uuid, $data->amount),
                JournalLineData::credit($data->categoryAccountUuid, $data->amount),
            ];
        }

        return $this->ledger->postEntry(
            new ManualJournalEntryData(
                companyUuid: $companyUuid,
                date: $data->date,
                description: $data->description,
                lines: $lines,
                createdBy: $actorId,
            ),
            JournalSource::CaptureIncome
        );
    }

    public function captureExpense(string $companyUuid, CaptureTransactionData $data, int $actorId): JournalEntry
    {
        $this->assertActiveSubscription($companyUuid);
        $this->assertPositiveAmount($data);

        $offsetAccount = $this->accounts->getByCode($companyUuid, $data->paymentMethod->accountCode());
        $vat = $this->resolveVatPortion($companyUuid, $data);

        if ($vat->isPositive()) {
            $vatAccount = $this->accounts->getByCode($companyUuid, ChartOfAccountsTemplate::CODE_VAT_INPUT);
            $net = $data->amount->subtract($vat);

            $lines = [
                JournalLineData::debit($data->categoryAccountUuid, $net),
                JournalLineData::debit($vatAccount->uuid, $vat),
                JournalLineData::credit($offsetAccount->uuid, $data->amount),
            ];
        } else {
            $lines = [
                JournalLineData::debit($data->categoryAccountUuid, $data->amount),
                JournalLineData::credit($offsetAccount->uuid, $data->amount),
            ];
        }

        return $this->ledger->postEntry(
            new ManualJournalEntryData(
                companyUuid: $companyUuid,
                date: $data->date,
                description: $data->description,
                lines: $lines,
                createdBy: $actorId,
            ),
            JournalSource::CaptureExpense
        );
    }

    /**
     * Resolves the VAT portion of a VAT-inclusive capture, rejecting
     * the attempt outright if the company isn't flagged VAT-registered
     * (a client-facing form should never show VAT fields for such a
     * company in the first place, but this is the server-side backstop).
     * Returns Money::zero() when $data->includesVat is false, or when
     * the computed VAT portion rounds down to nothing - either way the
     * caller falls back to a plain 2-line entry.
     *
     * @throws ValidationException
     */
    private function resolveVatPortion(string $companyUuid, CaptureTransactionData $data): Money
    {
        if (! $data->includesVat) {
            return Money::zero();
        }

        $settings = $this->companySettings->findByCompanyUuid($companyUuid);

        if ($settings === null || ! $settings->isVatRegistered) {
            throw new ValidationException('This company is not registered for VAT.');
        }

        return VatCalculator::vatPortionOfInclusive($data->amount);
    }

    private function assertPositiveAmount(CaptureTransactionData $data): void
    {
        if (! $data->amount->isPositive()) {
            throw new ValidationException('Captured transaction amount must be greater than zero.');
        }
    }

    private function assertActiveSubscription(string $companyUuid): void
    {
        if (! $this->subscriptions->isActive($companyUuid)) {
            throw SubscriptionInactiveException::forCompany($companyUuid);
        }
    }
}
