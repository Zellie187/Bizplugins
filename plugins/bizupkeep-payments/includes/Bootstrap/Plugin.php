<?php

declare(strict_types=1);

namespace BizHub\Payments\Bootstrap;

use BizHub\Payments\Install\Migrator;
use BizHub\Payments\Install\Schema;

/**
 * BizUpKeep Payments' WordPress-facing bootstrap.
 *
 * Only ever runs once DependencyGuard confirms BizHub, BizUpKeep Core,
 * BizUpKeep Workflow, and BizUpKeep Bookkeeping are all active. By the
 * time this runs, BizHub has already booted and registered this
 * plugin's own service providers into its shared container - including
 * PaymentsAdminServiceProvider, which registers the admin menu itself
 * via BizHub's own provider boot lifecycle, not from here. This class
 * only wires up the remaining WordPress-facing surface (translations,
 * REST webhook routes) on top of services that already exist in that
 * container.
 *
 * @package BizHub\Payments\Bootstrap
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

        $this->maybeUpgradeDatabase();

        add_action('init', [$this, 'loadTextdomain']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Load plugin translations.
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'bizupkeep-payments',
            false,
            dirname(BIZUPKEEP_PAYMENTS_BASENAME) . '/languages'
        );
    }

    /**
     * Register REST API webhook routes.
     */
    public function registerRoutes(): void
    {
        require BIZUPKEEP_PAYMENTS_PATH . 'routes/api.php';
    }

    /**
     * Bring an already-active install's schema up to date. Mirrors
     * BizUpKeep Core's own boot-time upgrade hook (Activator only runs
     * on the WordPress activation hook, so a schema change shipped in
     * an update needs this counterpart to reach an already-active
     * site). Bypasses the DI container and constructs Migrator
     * directly from the global $wpdb, for the same reason Activator
     * does.
     */
    private function maybeUpgradeDatabase(): void
    {
        global $wpdb;

        $migrator = new Migrator($wpdb, new Schema());

        if ($migrator->needsMigration()) {
            $migrator->migrate();
        }
    }
}
