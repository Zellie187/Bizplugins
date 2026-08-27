<?php

declare(strict_types=1);

namespace BizHub\Payments\DTO;

/**
 * Everything a gateway's createCheckout() needs, independent of which
 * gateway is being called.
 *
 * @package BizHub\Payments\DTO
 */
final readonly class CheckoutRequest
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
        public string $reference,
        public string $description,
        public string $successUrl,
        public string $cancelUrl,
        public string $failureUrl
    ) {
    }
}
