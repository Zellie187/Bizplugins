<?php

declare(strict_types=1);

namespace BizHub\Payments\Http\Controllers;

use BizHub\Framework\Logging\Logger;
use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Contracts\PaymentConfirmationServiceInterface;
use BizHub\Payments\Contracts\WebhookEventLogRepositoryInterface;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\WebhookEventOutcome;
use BizHub\Payments\Gateways\Yoco\YocoGateway;
use DateTimeImmutable;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public, unauthenticated REST endpoint Yoco delivers payment webhooks
 * to - signature verification (YocoGateway::verifyWebhookSignature())
 * is the authentication, not a WordPress nonce/capability check, since
 * the caller is Yoco's own servers, not a logged-in user.
 *
 * Always returns HTTP 200 once an event has been durably recorded,
 * even when this plugin's own downstream processing fails (see
 * PaymentConfirmationServiceInterface's docblock) - a non-2xx response
 * here would make Yoco retry delivery, which is the correct behaviour
 * for a delivery failure but actively wrong for an internal processing
 * failure after the money is already captured (see
 * PaymentAttemptRepositoryInterface::markSucceededOnce()).
 *
 * @package BizHub\Payments\Http\Controllers
 */
final class YocoWebhookController
{
    public function __construct(
        private readonly YocoGateway $gateway,
        private readonly WebhookEventLogRepositoryInterface $webhookLog,
        private readonly PaymentAttemptRepositoryInterface $attempts,
        private readonly PaymentConfirmationServiceInterface $confirmation,
        private readonly Logger $logger
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->gateway->verifyWebhookSignature($request)) {
            return new WP_REST_Response(['error' => 'Invalid signature.'], 401);
        }

        $event = $this->gateway->parseWebhookEvent($request);

        if ($event === null) {
            return new WP_REST_Response(null, 200);
        }

        $attempt = $this->attempts->findByGatewayReference(GatewayName::Yoco, $event->gatewayReference);

        try {
            $this->webhookLog->record(GatewayName::Yoco, $event->eventId, $attempt?->uuid, $request->get_body());
        } catch (Throwable) {
            // Best-effort audit trail only - never blocks processing.
        }

        if ($attempt === null) {
            return new WP_REST_Response(null, 200);
        }

        if ($event->outcome === WebhookEventOutcome::Failed) {
            $this->attempts->save($attempt->withFailed($event->failureReason));

            return new WP_REST_Response(null, 200);
        }

        if ($event->outcome !== WebhookEventOutcome::Succeeded) {
            return new WP_REST_Response(null, 200);
        }

        $claimed = $this->attempts->markSucceededOnce($attempt->uuid, new DateTimeImmutable());

        if (! $claimed) {
            // Either a duplicate delivery of an event already
            // processed, or a losing race against a concurrent
            // delivery - either way, this call has nothing left to do.
            return new WP_REST_Response(null, 200);
        }

        $confirmed = $this->attempts->findByUuid($attempt->uuid) ?? $attempt;

        if ($event->amountMinor !== null && $event->amountMinor !== $confirmed->amountMinor) {
            $this->attempts->save($confirmed->withAmountMismatch());

            $this->logger->critical('BizUpKeep Payments: webhook-confirmed amount did not match the expected amount.', [
                'attempt_uuid' => $confirmed->uuid,
                'expected_amount_minor' => $confirmed->amountMinor,
                'gateway_amount_minor' => $event->amountMinor,
            ]);

            return new WP_REST_Response(null, 200);
        }

        $this->confirmation->confirm($confirmed);

        return new WP_REST_Response(null, 200);
    }
}
