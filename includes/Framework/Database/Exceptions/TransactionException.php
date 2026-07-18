<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Exceptions;

/**
 * Thrown when a database transaction cannot be started,
 * committed, or rolled back.
 *
 * @package BizHub\Framework\Database\Exceptions
 */
final class TransactionException extends DatabaseException
{
}
