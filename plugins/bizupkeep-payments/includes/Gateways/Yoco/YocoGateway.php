<?php

declare(strict_types=1);

namespace BizHub\Payments\Gateways\Yoco;

use BizHub\Payments\Contracts\PaymentGatewayInterface;
use BizHub\Payments\DTO\CheckoutRequest;
use BizHub\Payments\DTO\CheckoutResult;
use BizHub\Payments\DTO\WebhookEvent;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\WebhookEventOutcome;
use BizHub\Payments\Exceptions\GatewayException;
use WP_REST_Request;

/**
 * Yoco's Checkout API integration
 * (https://developer.yoco.com/checkout-api-reference). Always calls
 * the API from the server (never exposes the secret key client-side),
 * and never trusts a checkout's successUrl redirect for confirmation -
 * only a verified webhook (see YocoSignatureVerifier) confirms
 * payment.
 *
 * Webhook payload shape (confirmed against Yoco's own docs during this
 * plugin's build): an outer envelope {id, type, payload: {...}}, where
 * the outer "id" is the *event's* id (used as WebhookEvent::$eventId,
 * for webhook_log dedup) and "payload.id" is the *checkout's* id - the
 * same value this gateway stored as PaymentAttempt::$gatewayReference
 * when the checkout was created, and the value this class extracts as
 * WebhookEvent::$gatewayReference to look the attempt back up.
 *
 * @package BizHub\Payments\Gateways\Yoco
 */
final class YocoGateway implements PaymentGatewayInterface
{
    private const API_BASE = 'https://payments.yoco.com/api';

    public function __construct(
        private readonly YocoSignatureVerifier $signatureVerifier
    ) {
    }

    public function name(): GatewayName
    {
        return GatewayName::Yoco;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        $response = wp_remote_post(self::API_BASE . '/checkouts', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey(),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'amount' => $request->amountMinor,
                'currency' => $request->currency,
                'successUrl' => $request->successUrl,
                'cancelUrl' => $request->cancelUrl,
                'failureUrl' => $request->failureUrl,
                'metadata' => [
                    'attempt_uuid' => $request->reference,
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new GatewayException('Yoco checkout request failed: ' . $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            throw new GatewayException(sprintf('Yoco checkout request returned HTTP %d.', $status));
        }

        $checkoutId = $body['id'] ?? null;
        $redirectUrl = $body['redirectUrl'] ?? null;

        if (! is_string($checkoutId) || $checkoutId === '' || ! is_string($redirectUrl) || $redirectUrl === '') {
            throw new GatewayException('Yoco checkout response did not include an id and redirectUrl.');
        }

        return new CheckoutResult($checkoutId, $redirectUrl);
    }

    public function verifyWebhookSignature(WP_REST_Request $request): bool
    {
        return $this->signatureVerifier->verify($request, $request->get_body(), $this->webhookSecret());
    }

    public function parseWebhookEvent(WP_REST_Request $request): ?WebhookEvent
    {
        $body = json_decode($request->get_body(), true);

        if (! is_array($body)) {
            return null;
        }

        $eventId = $body['id'] ?? null;
        $type = $body['type'] ?? null;
        $payload = $body['payload'] ?? null;

        if (! is_string($eventId) || ! is_string($type) || ! is_array($payload)) {
            return null;
        }

        $checkoutId = $payload['id'] ?? null;

        if (! is_string($checkoutId) || $checkoutId === '') {
            return null;
        }

        $outcome = match ($type) {
            'payment.succeeded' => WebhookEventOutcome::Succeeded,
            'payment.failed' => WebhookEventOutcome::Failed,
            default => null,
        };

        if ($outcome === null) {
            return null;
        }

        $amountMinor = isset($payload['amount']) && is_numeric($payload['amount'])
            ? (int) $payload['amount']
            : null;

        $failureReason = isset($payload['failureReason']) ? (string) $payload['failureReason'] : '';

        return new WebhookEvent($eventId, $checkoutId, $outcome, $amountMinor, $failureReason);
    }

    private function secretKey(): string
    {
        $key = get_option('bizupkeep_payments_yoco_secret_key');

        return is_string($key) ? $key : '';
    }

    private function webhookSecret(): string
    {
        $key = get_option('bizupkeep_payments_yoco_webhook_secret');

        return is_string($key) ? $key : '';
    }
}
