<?php

declare(strict_types=1);

namespace BizHub\Payments\Exceptions;

/**
 * Raised when a payment gateway's API rejects a request (e.g.
 * checkout creation failed) or a webhook payload cannot be verified/
 * parsed.
 *
 * @package BizHub\Payments\Exceptions
 */
final class GatewayException extends PaymentsException
{
}
