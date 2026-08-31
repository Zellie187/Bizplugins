<?php

declare(strict_types=1);

namespace BizHub\Payments\DTO;

use BizHub\Payments\Entities\PaymentAttempt;

/**
 * What PaymentAttemptServiceInterface's start methods return: the
 * persisted attempt plus the one-time redirect URL the caller must
 * send the client's browser to. Deliberately not folded into
 * PaymentAttempt itself - the redirect URL is a point-in-time value
 * from the gateway's createCheckout() response, not state this plugin
 * owns or needs to reconstruct later (a resumed/re-fetched attempt has
 * no reason to know it).
 *
 * @package BizHub\Payments\DTO
 */
final readonly class PaymentAttemptStart
{
    public function __construct(
        public PaymentAttempt $attempt,
        public string $redirectUrl
    ) {
    }
}
