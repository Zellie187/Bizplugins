<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Exceptions;

use Throwable;

/**
 * Thrown when a database query fails.
 *
 * @package BizHub\Framework\Database\Exceptions
 */
final class QueryException extends DatabaseException
{
    /**
     * @var string
     */
    private string $sql;

    /**
     * @param string         $message
     * @param string         $sql
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        string $sql = '',
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );

        $this->sql = $sql;
    }

    /**
     * Return the SQL that caused the exception.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * {@inheritDoc}
     */
    public static function fromThrowable(Throwable $throwable): static
    {
        return new static($throwable->getMessage(), '', $throwable);
    }
}
