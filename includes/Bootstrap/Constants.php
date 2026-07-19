<?php

declare(strict_types=1);

namespace BizHub\Workflow\Bootstrap;

/**
 * Defines plugin-wide path constants.
 *
 * @package BizHub\Workflow\Bootstrap
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
        self::define('BIZUPKEEP_WORKFLOW_CONFIG_PATH', BIZUPKEEP_WORKFLOW_PATH . 'config/');
        self::define('BIZUPKEEP_WORKFLOW_ASSETS_PATH', BIZUPKEEP_WORKFLOW_PATH . 'assets/');
        self::define('BIZUPKEEP_WORKFLOW_INCLUDES_PATH', BIZUPKEEP_WORKFLOW_PATH . 'includes/');
        self::define('BIZUPKEEP_WORKFLOW_TEMPLATE_PATH', BIZUPKEEP_WORKFLOW_PATH . 'templates/');
        self::define('BIZUPKEEP_WORKFLOW_LANGUAGE_PATH', BIZUPKEEP_WORKFLOW_PATH . 'languages/');
        self::define('BIZUPKEEP_WORKFLOW_STORAGE_PATH', BIZUPKEEP_WORKFLOW_PATH . 'storage/');
        self::define('BIZUPKEEP_WORKFLOW_RESOURCES_PATH', BIZUPKEEP_WORKFLOW_PATH . 'resources/');
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
