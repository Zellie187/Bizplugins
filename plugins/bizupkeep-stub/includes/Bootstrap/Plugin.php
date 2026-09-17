<?php

declare(strict_types=1);

namespace BizHub\Stub\Bootstrap;

use BizHub\Stub\Install\Migrator;
use BizHub\Stub\Install\Schema;

/**
 * BizUpKeep Stub's WordPress-facing bootstrap.
 *
 * Only ever runs once DependencyGuard confirms BizHub, BizUpKeep Core,
 * and BizUpKeep Bookkeeping are all active. By the time this runs,
 * BizHub has already booted and registered this plugin's own service
 * providers into its shared container - including StubAdminServiceProvider,
 * which registers the admin menu itself via BizHub's own provider boot
 * lifecycle, not from here. This class only wires up the remaining
 * WordPress-facing surface (translations, REST routes) on top of
 * services that already exist in that container.
 *
 * @package BizHub\Stub\Bootstrap
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        Constants::register();

        $this->maybeUpgradeDatabase();

        add_action('init', [$this, 'loadTextdomain']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'bizupkeep-stub',
            false,
            dirname(BIZUPKEEP_STUB_BASENAME) . '/languages'
        );
    }

    public function registerRoutes(): void
    {
        require BIZUPKEEP_STUB_PATH . 'routes/api.php';
    }

    /**
     * Bring an already-active install's schema up to date - mirrors
     * bizupkeep-payments' own boot-time upgrade hook.
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
