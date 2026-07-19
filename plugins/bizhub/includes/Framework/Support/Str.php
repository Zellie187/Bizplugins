<?php

declare(strict_types=1);

namespace BizHub\Framework\Support;

/**
 * String helper utilities.
 *
 * @package BizHub\Framework\Support
 */
final class Str
{
    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Determine whether a string contains a given substring.
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
    }

    /**
     * Determine whether a string starts with a given substring.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    /**
     * Determine whether a string ends with a given substring.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    /**
     * Convert a string to snake_case.
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        $value = preg_replace(
            '/(.)(?=[A-Z])/u',
            '$1' . $delimiter,
            $value
        ) ?? $value;

        return mb_strtolower($value);
    }

    /**
     * Convert a string to camelCase.
     */
    public static function camel(string $value): string
    {
        $studly = self::studly($value);

        return lcfirst($studly);
    }

    /**
     * Convert a string to StudlyCase.
     */
    public static function studly(string $value): string
    {
        $words = preg_split('/[\s_\-]+/', $value) ?: [];

        $words = array_map(
            static fn (string $word): string => ucfirst(mb_strtolower($word)),
            array_filter($words, static fn (string $word): bool => $word !== '')
        );

        return implode('', $words);
    }

    /**
     * Convert a string to a URL-friendly slug.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = mb_strtolower(trim($value));

        $value = preg_replace('/[^a-z0-9]+/u', $separator, $value) ?? $value;

        return trim($value, $separator);
    }

    /**
     * Truncate a string to a given length, appending a suffix if truncated.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    /**
     * Generate a random alphanumeric string.
     */
    public static function random(int $length = 16): string
    {
        $bytes = random_bytes((int) ceil($length / 2));

        return substr(bin2hex($bytes), 0, $length);
    }
}
