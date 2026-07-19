<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for all database-related errors.
 *
 * All framework database exceptions should extend this class.
 *
 * @package BizHub\Framework\Database\Exceptions
 */
class DatabaseException extends RuntimeException
{
    /**
     * Create a new database exception.
     *
     * @param string              $message
     * @param int                 $code
     * @param Throwable|null      $previous
     */
    public function __construct(
        string $message = 'A database error occurred.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous
        );
    }

    /**
     * Create an exception from another throwable.
     *
     * Subclasses with an incompatible constructor (a different
     * parameter order or count) must override this method - see
     * ConnectionException and QueryException.
     *
     * @param Throwable $throwable
     *
     * @return static
     */
    public static function fromThrowable(
        Throwable $throwable
    ): static {
        // @phpstan-ignore new.static
        return new static(
            $throwable->getMessage(),
            (int) $throwable->getCode(),
            $throwable
        );
    }
}
