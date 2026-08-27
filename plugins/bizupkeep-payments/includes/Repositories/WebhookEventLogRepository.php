<?php

declare(strict_types=1);

namespace BizHub\Payments\Repositories;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Payments\Contracts\WebhookEventLogRepositoryInterface;
use BizHub\Payments\Enums\GatewayName;
use DateTimeImmutable;

/**
 * @package BizHub\Payments\Repositories
 */
final class WebhookEventLogRepository implements WebhookEventLogRepositoryInterface
{
    private const TABLE = 'bizhub_payments_webhook_log';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function record(GatewayName $gateway, string $eventId, ?string $paymentAttemptUuid, string $payload): void
    {
        $criteria = ['gateway' => $gateway->value, 'event_id' => $eventId];

        if ($this->database->exists(self::TABLE, $criteria)) {
            return;
        }

        $this->database->insert(self::TABLE, [
            'gateway' => $gateway->value,
            'event_id' => $eventId,
            'payment_attempt_uuid' => $paymentAttemptUuid,
            'payload' => $payload,
            'received_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
