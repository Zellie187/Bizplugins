<?php

declare(strict_types=1);

namespace BizHub\Payments\Http\Controllers;

use BizHub\Framework\Logging\Logger;
use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Contracts\PaymentConfirmationServiceInterface;
use BizHub\Payments\Contracts\PaymentGatewayInterface;
use BizHub\Payments\Contracts\WebhookEventLogRepositoryInterface;
use BizHub\Payments\Gateways\Yoco\YocoGateway;

/**
 * Public, unauthenticated REST endpoint Yoco delivers payment webhooks
 * to - see AbstractWebhookController for the shared handling logic.
 *
 * @package BizHub\Payments\Http\Controllers
 */
final class YocoWebhookController extends AbstractWebhookController
{
    public function __construct(
        private readonly YocoGateway $yocoGateway,
        WebhookEventLogRepositoryInterface $webhookLog,
        PaymentAttemptRepositoryInterface $attempts,
        PaymentConfirmationServiceInterface $confirmation,
        Logger $logger
    ) {
        parent::__construct($webhookLog, $attempts, $confirmation, $logger);
    }

    protected function gateway(): PaymentGatewayInterface
    {
        return $this->yocoGateway;
    }
}
