<?php

declare(strict_types=1);

namespace BizHub\Framework\Support;

/**
 * Array helper utilities.
 *
 * @package BizHub\Framework\Support
 */
final class Arr
{
    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Determine whether a dot-notation key exists within an array.
     *
     * @param array<array-key,mixed> $array
     */
    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        $segments = explode('.', $key);
        $value = $array;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Retrieve a value from an array using dot notation.
     *
     * @param array<array-key,mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        $value = $array;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a value within an array using dot notation.
     *
     * @param array<array-key,mixed> $array
     *
     * @return array<array-key,mixed>
     */
    public static function set(array $array, string $key, mixed $value): array
    {
        $segments = explode('.', $key);
        $target = &$array;

        foreach ($segments as $segment) {
            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;

        return $array;
    }

    /**
     * Return only the given keys from an array.
     *
     * @param array<array-key,mixed> $array
     * @param array<int,array-key>   $keys
     *
     * @return array<array-key,mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Return an array without the given keys.
     *
     * @param array<array-key,mixed> $array
     * @param array<int,array-key>   $keys
     *
     * @return array<array-key,mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Pluck a single column of values from an array of arrays.
     *
     * @param array<int,array<array-key,mixed>> $array
     *
     * @return array<int,mixed>
     */
    public static function pluck(array $array, string $key): array
    {
        return array_map(
            static fn (array $item): mixed => self::get($item, $key),
            $array
        );
    }

    /**
     * Wrap a value in an array if it is not already one.
     *
     * @return array<int|array-key,mixed>
     */
    public static function wrap(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return $value === null ? [] : [$value];
    }

    /**
     * Determine whether an array is a list (sequential integer keys).
     *
     * @param array<array-key,mixed> $array
     */
    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }
}
