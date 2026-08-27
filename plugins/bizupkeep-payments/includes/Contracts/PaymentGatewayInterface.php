<?php

declare(strict_types=1);

namespace BizHub\Payments\Contracts;

use BizHub\Payments\DTO\CheckoutRequest;
use BizHub\Payments\DTO\CheckoutResult;
use BizHub\Payments\DTO\WebhookEvent;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Exceptions\GatewayException;
use WP_REST_Request;

/**
 * A payment gateway integration. One implementation per supported
 * gateway (Gateways/Yoco/YocoGateway, Gateways/SnapScan/
 * SnapScanGateway) - everything else in this plugin depends only on
 * this interface, never on a concrete gateway.
 *
 * @package BizHub\Payments\Contracts
 */
interface PaymentGatewayInterface
{
    public function name(): GatewayName;

    /**
     * Create a hosted checkout for the given amount and return the
     * URL to redirect the client's browser to.
     *
     * @throws GatewayException If the gateway's API rejects the request.
     */
    public function createCheckout(CheckoutRequest $request): CheckoutResult;

    /**
     * Verify that an incoming webhook request genuinely originated
     * from this gateway (signature check) before any payload data is
     * trusted.
     */
    public function verifyWebhookSignature(WP_REST_Request $request): bool;

    /**
     * Parse an already-signature-verified webhook request into a
     * gateway-agnostic event, or null if the event type is not one
     * this plugin acts on (e.g. a gateway sends other event types this
     * integration does not subscribe to).
     */
    public function parseWebhookEvent(WP_REST_Request $request): ?WebhookEvent;
}
