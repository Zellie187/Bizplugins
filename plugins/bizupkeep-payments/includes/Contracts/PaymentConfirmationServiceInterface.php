<?php

declare(strict_types=1);

namespace BizHub\Payments\Contracts;

use BizHub\Payments\Entities\PaymentAttempt;

/**
 * The critical-path service: everything that must happen once a
 * gateway webhook has atomically confirmed a payment succeeded (see
 * PaymentAttemptRepositoryInterface::markSucceededOnce()) - issuing
 * and paying an Invoice against A2Z's Internal Books company, then
 * either advancing the underlying workflow instance
 * (Registration/Amendment/Annual Return) or extending a Bookkeeping
 * Monthly subscription.
 *
 * @package BizHub\Payments\Contracts
 */
interface PaymentConfirmationServiceInterface
{
    /**
     * Run every downstream step for an attempt whose status has
     * already been atomically transitioned to Succeeded. Never throws
     * for a downstream-step failure (e.g. the workflow was no longer
     * AwaitingPayment by the time this ran) - it logs and leaves
     * $attempt->fulfilledAt null so PaymentsDashboardPage can surface
     * it for manual staff retry, since the money has already been
     * captured and a webhook-triggered exception must never look like
     * the gateway should retry delivery.
     */
    public function confirm(PaymentAttempt $attempt): void;
}
