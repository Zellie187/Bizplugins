<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\Query;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Represents the result of a database query.
 *
 * Provides a strongly typed wrapper around database rows while
 * remaining independent of the underlying database driver.
 *
 * @implements IteratorAggregate<int, array<string,mixed>>
 *
 * @package BizHub\Framework\Database\Query
 */
final readonly class QueryResult implements IteratorAggregate, Countable
{
    /**
     * Create a new query result.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(
        private array $rows
    ) {
    }

    /**
     * Determine whether the result contains rows.
     */
    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * Determine whether the result contains one or more rows.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Return the first row.
     *
     * @return array<string,mixed>|null
     */
    public function first(): ?array
    {
        return $this->rows[0] ?? null;
    }

    /**
     * Return the last row.
     *
     * @return array<string,mixed>|null
     */
    public function last(): ?array
    {
        if ($this->rows === []) {
            return null;
        }

        return $this->rows[array_key_last($this->rows)];
    }

    /**
     * Return all rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->rows;
    }

    /**
     * Number of rows.
     */
    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * Iterator implementation.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }

    /**
     * Convert the result to an array.
     *
     * @return array<int,array<string,mixed>>
     */
    public function toArray(): array
    {
        return $this->rows;
    }
}
