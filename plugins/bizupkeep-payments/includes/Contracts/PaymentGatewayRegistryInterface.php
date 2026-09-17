<?php

declare(strict_types=1);

namespace BizHub\Payments\Contracts;

use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Exceptions\ValidationException;

/**
 * Resolves a GatewayName to its PaymentGatewayInterface implementation
 * - the one place PaymentAttemptService and each webhook controller
 * go to find "the gateway", so adding a third gateway later is a
 * one-line change here rather than a repeated match() at every call
 * site.
 *
 * @package BizHub\Payments\Contracts
 */
interface PaymentGatewayRegistryInterface
{
    /**
     * @throws ValidationException If no gateway is registered for $name (e.g. a gateway
     *                              enum case exists but its implementation isn't wired
     *                              into the container yet, as SnapScan is until it ships).
     */
    public function get(GatewayName $name): PaymentGatewayInterface;
}
