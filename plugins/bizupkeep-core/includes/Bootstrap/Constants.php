<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Bootstrap;

/**
 * Defines plugin-wide path constants beyond the core set already
 * defined in bizupkeep-core.php (BIZUPKEEP_CORE_VERSION/_FILE/_PATH/
 * _URL/_BASENAME).
 *
 * @package BizUpKeep\Core\Bootstrap
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
        self::define('BIZUPKEEP_CORE_ASSETS_PATH', BIZUPKEEP_CORE_PATH . 'assets/');
        self::define('BIZUPKEEP_CORE_INCLUDES_PATH', BIZUPKEEP_CORE_PATH . 'includes/');
        self::define('BIZUPKEEP_CORE_LANGUAGE_PATH', BIZUPKEEP_CORE_PATH . 'languages/');
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
