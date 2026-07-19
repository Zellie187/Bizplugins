<?php

declare(strict_types=1);

namespace BizHub\Workflow\Bootstrap;

use BizHub\Workflow\Admin\WorkflowAdminMenu;

/**
 * BizUpKeep Workflow's WordPress-facing bootstrap.
 *
 * Only ever runs once DependencyGuard confirms BizHub is loaded and
 * BizUpKeep Core is active. By the time this runs, BizHub (if active)
 * has already booted and registered BizHub\Workflow\Providers\
 * WorkflowServiceProvider into its shared container - this class only
 * wires up the WordPress-facing surface (translations, REST routes,
 * admin screens) on top of services that already exist in that
 * container.
 *
 * @package BizHub\Workflow\Bootstrap
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    /**
     * Prevent direct instantiation; use instance().
     */
    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Boot the WordPress-facing side of the plugin.
     */
    public function boot(): void
    {
        Constants::register();

        add_action('init', [$this, 'loadTextdomain']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_menu', [new WorkflowAdminMenu(), 'register']);
    }

    /**
     * Load plugin translations.
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'bizupkeep-workflow',
            false,
            dirname(BIZUPKEEP_WORKFLOW_BASENAME) . '/languages'
        );
    }

    /**
     * Register REST API routes.
     */
    public function registerRoutes(): void
    {
        require BIZUPKEEP_WORKFLOW_PATH . 'routes/api.php';
    }
}
