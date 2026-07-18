<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\ValueObjects;

use InvalidArgumentException;

/**
 * Represents a single ORDER BY clause.
 *
 * @package BizHub\Framework\Database\ValueObjects
 */
final readonly class QueryOrder
{
    public string $direction;

    /**
     * @param string $column    Column name.
     * @param string $direction Sort direction ('ASC'|'DESC').
     */
    public function __construct(
        public string $column,
        string $direction = 'ASC',
    ) {
        $direction = strtoupper($direction);

        if (! in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException(
                'Order direction must be ASC or DESC.'
            );
        }

        $this->direction = $direction;
    }

    /**
     * Render the order clause as an SQL fragment.
     */
    public function toSql(): string
    {
        return sprintf('%s %s', $this->column, $this->direction);
    }
}
