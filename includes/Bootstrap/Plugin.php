<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Bootstrap;

/**
 * BizUpKeep Core's WordPress-facing bootstrap.
 *
 * Only ever runs once DependencyGuard confirms BizHub is loaded. By
 * the time this runs, BizHub - if active - has already built its
 * container and booted every provider, including
 * BizUpKeep\Core\Providers\CoreServiceProvider. This class only wires
 * up the WordPress-facing surface (translations, asset loading) on
 * top of services that already exist in that container.
 *
 * @package BizUpKeep\Core\Bootstrap
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

        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'loadTextdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Fires once Core has finished initialising.
     */
    public function init(): void
    {
        do_action('bizupkeep_core_init');
    }

    /**
     * Load plugin translations.
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'bizupkeep-core',
            false,
            dirname(BIZUPKEEP_CORE_BASENAME) . '/languages'
        );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueueFrontendAssets(): void
    {
        $style = BIZUPKEEP_CORE_PATH . 'assets/css/frontend.css';
        $script = BIZUPKEEP_CORE_PATH . 'assets/js/frontend.js';

        if (file_exists($style)) {
            wp_enqueue_style(
                'bizupkeep-core',
                BIZUPKEEP_CORE_URL . 'assets/css/frontend.css',
                [],
                BIZUPKEEP_CORE_VERSION
            );
        }

        if (file_exists($script)) {
            wp_enqueue_script(
                'bizupkeep-core',
                BIZUPKEEP_CORE_URL . 'assets/js/frontend.js',
                [],
                BIZUPKEEP_CORE_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueueAdminAssets(): void
    {
        $style = BIZUPKEEP_CORE_PATH . 'assets/css/admin.css';
        $script = BIZUPKEEP_CORE_PATH . 'assets/js/admin.js';

        if (file_exists($style)) {
            wp_enqueue_style(
                'bizupkeep-core-admin',
                BIZUPKEEP_CORE_URL . 'assets/css/admin.css',
                [],
                BIZUPKEEP_CORE_VERSION
            );
        }

        if (file_exists($script)) {
            wp_enqueue_script(
                'bizupkeep-core-admin',
                BIZUPKEEP_CORE_URL . 'assets/js/admin.js',
                [],
                BIZUPKEEP_CORE_VERSION,
                true
            );
        }
    }
}
