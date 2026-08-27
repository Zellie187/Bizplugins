<?php

declare(strict_types=1);

namespace BizHub\Payments\DTO;

/**
 * What a gateway's createCheckout() returns, independent of which
 * gateway created it.
 *
 * @package BizHub\Payments\DTO
 */
final readonly class CheckoutResult
{
    public function __construct(
        public string $gatewayReference,
        public string $redirectUrl
    ) {
    }
}
