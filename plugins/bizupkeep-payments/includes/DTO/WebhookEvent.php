<?php

declare(strict_types=1);

namespace BizHub\Payments\DTO;

use BizHub\Payments\Enums\WebhookEventOutcome;

/**
 * A gateway-agnostic parse of an incoming webhook payload, produced by
 * each gateway's own parseWebhookEvent() from its own wire format.
 * $amountMinor is null when the payload genuinely doesn't carry one
 * (e.g. a Pending event) - the amount-mismatch check only ever runs
 * against a Succeeded event, where it is always present.
 *
 * @package BizHub\Payments\DTO
 */
final readonly class WebhookEvent
{
    public function __construct(
        public string $eventId,
        public string $gatewayReference,
        public WebhookEventOutcome $outcome,
        public ?int $amountMinor,
        public string $failureReason = ''
    ) {
    }
}
