<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Exceptions;

/**
 * Raised when caller-supplied input fails domain validation (e.g. an
 * empty description, a non-positive amount).
 *
 * @package BizHub\Bookkeeping\Exceptions
 */
final class ValidationException extends BookkeepingException
{
}
