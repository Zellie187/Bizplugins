<?php

declare(strict_types=1);

namespace BizHub\Payments\Contracts;

use BizHub\Payments\Entities\PaymentAttempt;
use BizHub\Payments\Enums\GatewayName;
use DateTimeImmutable;

/**
 * @package BizHub\Payments\Contracts
 */
interface PaymentAttemptRepositoryInterface
{
    public function findByUuid(string $uuid): ?PaymentAttempt;

    public function findByGatewayReference(GatewayName $gateway, string $gatewayReference): ?PaymentAttempt;

    /**
     * @return PaymentAttempt[]
     */
    public function findAll(): array;

    /**
     * Persist a non-status field change (attaching a customer/invoice,
     * marking fulfilled) or a Created row's insert. Never used for the
     * Redirected -> Succeeded transition itself - see
     * markSucceededOnce() for why that needs a different mechanism.
     */
    public function save(PaymentAttempt $attempt): PaymentAttempt;

    /**
     * Atomically transition a payment attempt from Redirected to
     * Succeeded, matching on the exact prior status rather than
     * excluding Succeeded - a single UPDATE ... WHERE uuid = ? AND
     * status = 'redirected' statement, so a duplicate or racing
     * webhook delivery for the same gateway_reference can only ever
     * win this update once. Returns true only if this call actually
     * changed the row (i.e. this call is the one that gets to
     * proceed to PaymentConfirmationService::confirm()) - false means
     * the payment was already handled (by an earlier delivery of the
     * same event, or a concurrent one), and the caller must not
     * re-process it.
     */
    public function markSucceededOnce(string $uuid, DateTimeImmutable $confirmedAt): bool;
}
