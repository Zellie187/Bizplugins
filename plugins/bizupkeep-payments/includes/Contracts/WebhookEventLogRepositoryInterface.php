<?php

declare(strict_types=1);

namespace BizHub\Payments\Contracts;

use BizHub\Payments\Enums\GatewayName;

/**
 * An insert-only audit trail of every webhook delivery received - for
 * support/debugging only, never the idempotency gate (that's
 * PaymentAttemptRepositoryInterface::markSucceededOnce()'s job).
 *
 * @package BizHub\Payments\Contracts
 */
interface WebhookEventLogRepositoryInterface
{
    /**
     * Record a delivery. Silently ignores a duplicate (gateway,
     * event_id) pair rather than throwing - a gateway retrying the
     * same delivery is expected behaviour, not an error to surface.
     */
    public function record(GatewayName $gateway, string $eventId, ?string $paymentAttemptUuid, string $payload): void;
}
