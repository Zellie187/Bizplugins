<?php

declare(strict_types=1);

namespace BizHub\Payments\Exceptions;

/**
 * Raised when caller-supplied input fails domain validation, or a
 * requested payment attempt/service cannot be resolved for the
 * requesting client.
 *
 * @package BizHub\Payments\Exceptions
 */
final class ValidationException extends PaymentsException
{
}
