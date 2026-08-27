<?php

declare(strict_types=1);

namespace BizHub\Payments\Services;

use BizHub\Payments\Contracts\PaymentGatewayInterface;
use BizHub\Payments\Contracts\PaymentGatewayRegistryInterface;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Exceptions\ValidationException;
use BizHub\Payments\Gateways\Yoco\YocoGateway;

/**
 * @package BizHub\Payments\Services
 */
final class PaymentGatewayRegistry implements PaymentGatewayRegistryInterface
{
    public function __construct(
        private readonly YocoGateway $yocoGateway
    ) {
    }

    public function get(GatewayName $name): PaymentGatewayInterface
    {
        return match ($name) {
            GatewayName::Yoco => $this->yocoGateway,
            GatewayName::SnapScan => throw new ValidationException('SnapScan is not available yet.'),
        };
    }
}
