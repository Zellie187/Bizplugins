<?php

declare(strict_types=1);

namespace BizHub\Framework\Database\ValueObjects;

/**
 * Represents a single bound query parameter.
 *
 * @package BizHub\Framework\Database\ValueObjects
 */
final readonly class QueryParameter
{
    /**
     * @param mixed $value Bound value.
     */
    public function __construct(
        public mixed $value,
    ) {
    }

    /**
     * Return the wpdb-style placeholder for this parameter's value type.
     */
    public function placeholder(): string
    {
        return match (true) {
            is_int($this->value) => '%d',
            is_float($this->value) => '%f',
            default => '%s',
        };
    }
}
