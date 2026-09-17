<?php

declare(strict_types=1);

namespace BizHub\Stub\Bootstrap;

/**
 * Defines plugin-wide path constants.
 *
 * @package BizHub\Stub\Bootstrap
 */
final class Constants
{
    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Register plugin constants.
     */
    public static function register(): void
    {
        self::define('BIZUPKEEP_STUB_CONFIG_PATH', BIZUPKEEP_STUB_PATH . 'config/');
        self::define('BIZUPKEEP_STUB_LANGUAGE_PATH', BIZUPKEEP_STUB_PATH . 'languages/');
    }

    /**
     * Define a constant if it does not already exist.
     */
    private static function define(string $name, mixed $value): void
    {
        if (! defined($name)) {
            define($name, $value);
        }
    }
}
