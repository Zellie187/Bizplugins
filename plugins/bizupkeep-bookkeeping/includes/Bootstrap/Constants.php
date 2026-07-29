<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Bootstrap;

/**
 * Defines plugin-wide path constants.
 *
 * This plugin has no client-facing templates, no runtime file storage
 * (CSV exports stream straight to the browser) and no static assets of
 * its own, so unlike BizUpKeep Workflow it only needs config/includes/
 * language path constants.
 *
 * @package BizHub\Bookkeeping\Bootstrap
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
        self::define('BIZUPKEEP_BOOKKEEPING_CONFIG_PATH', BIZUPKEEP_BOOKKEEPING_PATH . 'config/');
        self::define('BIZUPKEEP_BOOKKEEPING_INCLUDES_PATH', BIZUPKEEP_BOOKKEEPING_PATH . 'includes/');
        self::define('BIZUPKEEP_BOOKKEEPING_LANGUAGE_PATH', BIZUPKEEP_BOOKKEEPING_PATH . 'languages/');
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
