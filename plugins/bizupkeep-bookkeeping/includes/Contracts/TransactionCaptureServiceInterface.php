<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\Entities\JournalEntry;

/**
 * The client-facing simplified capture API - the only entry point the
 * theme's "capture income/expense" form calls. Debit/credit mechanics
 * never surface here; each method builds the correctly-balanced 2-line
 * journal entry automatically.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface TransactionCaptureServiceInterface
{
    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function captureIncome(string $companyUuid, CaptureTransactionData $data, int $actorId): JournalEntry;

    /**
     * @throws \BizHub\Bookkeeping\Exceptions\ValidationException
     */
    public function captureExpense(string $companyUuid, CaptureTransactionData $data, int $actorId): JournalEntry;
}
