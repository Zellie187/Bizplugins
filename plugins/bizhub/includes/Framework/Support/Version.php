<?php

declare(strict_types=1);

namespace BizHub\Framework\Support;

/**
 * Plugin version information.
 *
 * @package BizHub\Framework\Support
 */
final class Version
{
    /**
     * Current plugin version.
     *
     * Must be kept in sync with the "Version" header in bizhub.php.
     */
    public const CURRENT = '0.2.0';

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Return the current plugin version.
     */
    public static function current(): string
    {
        return self::CURRENT;
    }

    /**
     * Compare the current version against another version string.
     *
     * @param string $version  Version to compare against.
     * @param string $operator Comparison operator (see version_compare()).
     */
    public static function compare(string $version, string $operator): bool
    {
        return version_compare(self::CURRENT, $version, $operator);
    }
}
