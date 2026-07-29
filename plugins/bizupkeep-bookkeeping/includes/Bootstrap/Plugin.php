<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Bootstrap;

/**
 * BizUpKeep Bookkeeping's WordPress-facing bootstrap.
 *
 * Only ever runs once DependencyGuard confirms BizHub is loaded and
 * BizUpKeep Core is active. By the time this runs, BizHub (if active)
 * has already booted and registered this plugin's own vertical
 * ServiceProviders into its shared container - this class only wires
 * up the WordPress-facing surface (translations, REST routes) on top
 * of services that already exist in that container.
 *
 * Unlike BizUpKeep Workflow, this plugin has only ONE admin screen, so
 * admin-menu registration is owned entirely by
 * BookkeepingAdminServiceProvider rather than duplicated here.
 *
 * @package BizHub\Bookkeeping\Bootstrap
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
    }

    /**
     * Load plugin translations.
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'bizupkeep-bookkeeping',
            false,
            dirname(BIZUPKEEP_BOOKKEEPING_BASENAME) . '/languages'
        );
    }

    /**
     * Register REST API routes.
     */
    public function registerRoutes(): void
    {
        require BIZUPKEEP_BOOKKEEPING_PATH . 'routes/api.php';
    }
}
