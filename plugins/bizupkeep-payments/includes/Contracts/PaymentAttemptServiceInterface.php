<?php

declare(strict_types=1);

namespace BizHub\Payments\Contracts;

use BizHub\Payments\DTO\PaymentAttemptStart;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Exceptions\GatewayException;
use BizHub\Payments\Exceptions\ValidationException;

/**
 * Client-facing entry points for starting a payment. Called directly
 * from astra-child's own nonce-protected POST form handlers (the same
 * "template_redirect handler calls a container service directly"
 * pattern already established for Bookkeeping's Invoicing tab) - not
 * exposed as a REST route, since nothing outside this WordPress
 * install ever needs to trigger one of these.
 *
 * @package BizHub\Payments\Contracts
 */
interface PaymentAttemptServiceInterface
{
    /**
     * Start a payment for a workflow-backed service (Registration,
     * Company Amendment, Annual Return) currently AwaitingPayment.
     *
     * @throws ValidationException If the workflow cannot be resolved, is not owned by
     *                              $wpUserId, is not AwaitingPayment, or its service has
     *                              no price configured yet.
     * @throws GatewayException    If the gateway rejects the checkout-creation request.
     */
    public function startForWorkflow(string $workflowUuid, int $wpUserId, GatewayName $gateway): PaymentAttemptStart;

    /**
     * Start a payment for the Bookkeeping Monthly subscription -
     * company-scoped rather than workflow-scoped, since this flow has
     * no underlying workflow instance.
     *
     * @throws ValidationException If the company cannot be resolved or is not owned by
     *                              $wpUserId, or the bookkeeping_monthly service has no
     *                              price configured yet.
     * @throws GatewayException    If the gateway rejects the checkout-creation request.
     */
    public function startForBookkeepingSubscription(
        string $companyUuid,
        int $wpUserId,
        GatewayName $gateway
    ): PaymentAttemptStart;
}
