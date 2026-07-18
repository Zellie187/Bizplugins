<?php

declare(strict_types=1);

namespace BizHub\Framework\Validation;

/**
 * Individual validation rule implementations.
 *
 * @package BizHub\Framework\Validation
 */
final class Rules
{
    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * The value must be present and not empty.
     */
    public static function required(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * The value must be a validly formatted email address.
     */
    public static function email(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * The value must be numeric.
     */
    public static function numeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    /**
     * The value must be an integer, or a numeric string containing one.
     */
    public static function integer(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit(ltrim($value, '-')));
    }

    /**
     * The value must be a boolean.
     */
    public static function boolean(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1'], true);
    }

    /**
     * The value must meet a minimum length (strings) or amount (numbers).
     */
    public static function min(mixed $value, int|float $min): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return (float) $value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * The value must not exceed a maximum length (strings) or amount (numbers).
     */
    public static function max(mixed $value, int|float $max): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return (float) $value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * The value must be one of the given options.
     *
     * @param array<int,mixed> $options
     */
    public static function in(mixed $value, array $options): bool
    {
        return in_array($value, $options, true);
    }
}
