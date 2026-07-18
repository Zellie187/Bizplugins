<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\ValueObjects;

/**
 * Represents a single WHERE condition within a query.
 *
 * @package BizHub\Framework\Database\ValueObjects
 */
final readonly class QueryCondition
{
    /**
     * @param string $column   Column name.
     * @param string $operator Comparison operator.
     * @param mixed  $value    Bound value.
     * @param string $boolean  Logical connector to the previous condition ('AND'|'OR').
     */
    public function __construct(
        public string $column,
        public string $operator,
        public mixed $value,
        public string $boolean = 'AND',
    ) {
    }

    /**
     * Render the condition as an SQL fragment using the given placeholder.
     */
    public function toSql(string $placeholder): string
    {
        return sprintf(
            '%s %s %s',
            $this->column,
            $this->operator,
            $placeholder
        );
    }
}
