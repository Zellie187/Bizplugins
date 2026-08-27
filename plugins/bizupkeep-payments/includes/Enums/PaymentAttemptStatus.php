<?php

declare(strict_types=1);

namespace BizHub\Payments\Enums;

/**
 * A payment attempt's lifecycle. The two transitions that matter for
 * correctness are Redirected -> Succeeded (the atomic compare-and-swap
 * PaymentAttemptRepository::markSucceededOnce() performs, matching on
 * the exact prior status so a duplicate/racing webhook delivery can
 * never double-process the same payment) and Succeeded -> fulfilled
 * (tracked separately via PaymentAttempt::$fulfilledAt, not a status
 * case - a payment can be genuinely Succeeded while the downstream
 * Invoice/workflow-confirmation step still failed and needs a manual
 * retry, which PaymentsDashboardPage surfaces).
 *
 * @package BizHub\Payments\Enums
 */
enum PaymentAttemptStatus: string
{
    case Created = 'created';
    case Redirected = 'redirected';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case AmountMismatch = 'amount_mismatch';
}
