<?php

declare(strict_types=1);

namespace BizHub\Payments\Bootstrap;

/**
 * Defines plugin-wide path constants.
 *
 * @package BizHub\Payments\Bootstrap
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
        self::define('BIZUPKEEP_PAYMENTS_CONFIG_PATH', BIZUPKEEP_PAYMENTS_PATH . 'config/');
        self::define('BIZUPKEEP_PAYMENTS_LANGUAGE_PATH', BIZUPKEEP_PAYMENTS_PATH . 'languages/');
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
