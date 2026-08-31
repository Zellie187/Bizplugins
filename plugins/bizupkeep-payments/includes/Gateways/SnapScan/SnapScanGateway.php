<?php

declare(strict_types=1);

namespace BizHub\Payments\Gateways\SnapScan;

use BizHub\Payments\Contracts\PaymentGatewayInterface;
use BizHub\Payments\DTO\CheckoutRequest;
use BizHub\Payments\DTO\CheckoutResult;
use BizHub\Payments\DTO\WebhookEvent;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\WebhookEventOutcome;
use BizHub\Payments\Exceptions\GatewayException;
use WP_REST_Request;

/**
 * SnapScan's merchant payment-request integration
 * (developer.getsnapscan.com). Unlike Yoco's Checkout API, creating a
 * "checkout" needs no outbound API call at all: SnapScan's own
 * payment page is a plain, publicly-browsable URL built from your
 * snapcode + a merchant reference + an amount
 * (https://pos.snapscan.io/qr/{snapcode}?id=...&amount=...&strict=true)
 * - that URL itself is both the gateway reference (via its `id` query
 * arg, which the webhook echoes back as `merchantReference`) and the
 * redirect URL.
 *
 * NEEDS LIVE RE-VERIFICATION before production use: built directly
 * from SnapScan's published API docs, since no SnapScan sandbox
 * credentials were available during this plugin's build to actually
 * exercise a real payment request/webhook round-trip (unlike Yoco,
 * which was verified against its live test API - see YocoGateway).
 *
 * @package BizHub\Payments\Gateways\SnapScan
 */
final class SnapScanGateway implements PaymentGatewayInterface
{
    private const PAYMENT_BASE_URL = 'https://pos.snapscan.io/qr/';

    public function __construct(
        private readonly SnapScanSignatureVerifier $signatureVerifier
    ) {
    }

    public function name(): GatewayName
    {
        return GatewayName::SnapScan;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        $snapCode = $this->snapCode();

        if ($snapCode === '') {
            throw new GatewayException('SnapScan snap code is not configured.');
        }

        $url = add_query_arg(
            [
                'id' => $request->reference,
                'amount' => $request->amountMinor,
                'strict' => 'true',
            ],
            self::PAYMENT_BASE_URL . rawurlencode($snapCode)
        );

        // The merchant reference we chose (the attempt's own uuid) IS
        // the gateway reference here - SnapScan has no separate
        // server-assigned checkout id the way Yoco does.
        return new CheckoutResult($request->reference, $url);
    }

    public function verifyWebhookSignature(WP_REST_Request $request): bool
    {
        return $this->signatureVerifier->verify($request, $request->get_body(), $this->webhookKey());
    }

    public function parseWebhookEvent(WP_REST_Request $request): ?WebhookEvent
    {
        // SnapScan delivers webhooks as application/x-www-form-urlencoded
        // with a single `payload` field containing JSON - WordPress's
        // REST server populates body params from $_POST for non-JSON
        // content types, so get_param() sees it like any other field.
        $payloadRaw = $request->get_param('payload');

        if (! is_string($payloadRaw)) {
            return null;
        }

        $payload = json_decode($payloadRaw, true);

        if (! is_array($payload)) {
            return null;
        }

        $merchantReference = $payload['merchantReference'] ?? null;
        $status = $payload['status'] ?? null;

        if (! is_string($merchantReference) || ! is_string($status)) {
            return null;
        }

        $outcome = match ($status) {
            'completed' => WebhookEventOutcome::Succeeded,
            'error' => WebhookEventOutcome::Failed,
            'pending' => WebhookEventOutcome::Pending,
            default => null,
        };

        if ($outcome === null) {
            return null;
        }

        $amountMinor = isset($payload['totalAmount']) && is_numeric($payload['totalAmount'])
            ? (int) $payload['totalAmount']
            : null;

        // SnapScan has no separate outer "event id" the way Yoco's
        // envelope does - this is only a stable key for the
        // webhook_log audit trail, never the idempotency gate itself
        // (that's markSucceededOnce()'s atomic status transition).
        $eventId = $merchantReference . ':' . substr(hash('sha256', $payloadRaw), 0, 16);

        return new WebhookEvent($eventId, $merchantReference, $outcome, $amountMinor);
    }

    private function snapCode(): string
    {
        $value = get_option('bizupkeep_payments_snapscan_snap_code');

        return is_string($value) ? $value : '';
    }

    private function webhookKey(): string
    {
        $value = get_option('bizupkeep_payments_snapscan_webhook_key');

        return is_string($value) ? $value : '';
    }
}
