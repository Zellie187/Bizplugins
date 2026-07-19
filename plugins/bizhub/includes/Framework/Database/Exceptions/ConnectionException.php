<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Exceptions;

use Throwable;

/**
 * Thrown when a database connection cannot be established or is lost.
 *
 * @package BizHub\Framework\Database\Exceptions
 */
final class ConnectionException extends DatabaseException
{
    /**
     * Create a new connection exception.
     *
     * @param string         $message
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message = 'Unable to establish a database connection.',
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function fromThrowable(Throwable $throwable): static
    {
        return new static($throwable->getMessage(), $throwable);
    }
}
