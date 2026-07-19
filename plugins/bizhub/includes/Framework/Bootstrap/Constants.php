<?php

declare(strict_types=1);

namespace BizHub\Framework\Bootstrap;

/**
 * Defines framework-wide constants.
 *
 * @package BizHub\Framework\Bootstrap
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
     * Register framework constants.
     *
     * @return void
     */
    public static function register(): void
    {
        self::define('BIZHUB_START_TIME', microtime(true));

        self::define(
            'BIZHUB_CONFIG_PATH',
            BIZHUB_PLUGIN_PATH . 'config/'
        );

        self::define(
            'BIZHUB_ASSETS_PATH',
            BIZHUB_PLUGIN_PATH . 'assets/'
        );

        self::define(
            'BIZHUB_INCLUDES_PATH',
            BIZHUB_PLUGIN_PATH . 'includes/'
        );

        self::define(
            'BIZHUB_TEMPLATE_PATH',
            BIZHUB_PLUGIN_PATH . 'templates/'
        );

        self::define(
            'BIZHUB_LANGUAGE_PATH',
            BIZHUB_PLUGIN_PATH . 'languages/'
        );

        self::define(
            'BIZHUB_STORAGE_PATH',
            BIZHUB_PLUGIN_PATH . 'storage/'
        );
    }

    /**
     * Define a constant if it does not already exist.
     *
     * @param string $name  Constant name.
     * @param mixed  $value Constant value.
     *
     * @return void
     */
    private static function define(string $name, mixed $value): void
    {
        if (! defined($name)) {
            define($name, $value);
        }
    }
}
