<?php

declare(strict_types=1);

namespace BizHub\Payments\Http\Controllers;

use BizHub\Framework\Logging\Logger;
use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Contracts\PaymentConfirmationServiceInterface;
use BizHub\Payments\Contracts\PaymentGatewayInterface;
use BizHub\Payments\Contracts\WebhookEventLogRepositoryInterface;
use BizHub\Payments\Gateways\SnapScan\SnapScanGateway;

/**
 * Public, unauthenticated REST endpoint SnapScan delivers payment
 * webhooks to - see AbstractWebhookController for the shared handling
 * logic.
 *
 * @package BizHub\Payments\Http\Controllers
 */
final class SnapScanWebhookController extends AbstractWebhookController
{
    public function __construct(
        private readonly SnapScanGateway $snapScanGateway,
        WebhookEventLogRepositoryInterface $webhookLog,
        PaymentAttemptRepositoryInterface $attempts,
        PaymentConfirmationServiceInterface $confirmation,
        Logger $logger
    ) {
        parent::__construct($webhookLog, $attempts, $confirmation, $logger);
    }

    protected function gateway(): PaymentGatewayInterface
    {
        return $this->snapScanGateway;
    }
}
