<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Install;

use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;

/**
 * Handles activation-time setup for BizUpKeep Core.
 *
 * Deliberately does not go through the DI container: activation must
 * work reliably as a standalone, synchronous WordPress hook callback,
 * independent of BizHub's boot lifecycle (BizHub may not have booted
 * yet in the same request - see Bootstrap\DependencyGuard). Schema
 * migration constructs its own Migrator/Schema directly from the
 * global $wpdb for the same reason - both are this plugin's own
 * classes, not a cross-plugin dependency.
 *
 * Service Catalog seeding is NOT done here, on purpose: it needs
 * BizHub\Framework\Database\Drivers\WordPressDatabase (via
 * ServiceRepositoryInterface), a class this plugin's own Composer
 * autoloader has no knowledge of - it only resolves if BizHub's
 * autoloader happens to already be registered in the same PHP
 * process. That held under manual one-at-a-time activation via
 * wp-admin, but broke under Plesk WP Toolkit's bulk install/activate
 * flow (PHP Fatal error: Class "BizHub\Framework\Database\Drivers\
 * WordPressDatabase" not found, thrown from this file). See
 * seedServiceCatalogOnce() below - called from bizupkeep-core.php's
 * 'bizhub/register_providers' callback instead, which only ever runs
 * while BizHub is actively executing that code itself (autoloader
 * guaranteed registered) and already hands over a fully-built
 * container with ServiceRepositoryInterface bound.
 *
 * @package BizUpKeep\Core\Install
 */
final class Activator
{
    private const VERSION_OPTION = 'bizupkeep_core_version';
    private const INSTALLED_OPTION = 'bizupkeep_core_installed';
    private const CATALOG_SEEDED_OPTION = 'bizupkeep_core_catalog_seeded';

    public function activate(): void
    {
        $this->setInstallTimestamp();
        $this->setPluginVersion();
        $this->createUploadDirectories();
        $this->migrate();
        (new RoleGrant())->install();

        flush_rewrite_rules();
    }

    private function migrate(): void
    {
        global $wpdb;

        (new Migrator($wpdb, new Schema()))->migrate();
    }

    /**
     * Seed the Service catalog's fixed row set, exactly once - safe to
     * call on every 'bizhub/register_providers' firing (i.e. every
     * request once BizHub boots), not just activation, since a fresh
     * install's first request past activation never actually ran the
     * old activation-time seed either. ServiceCatalogSeeder is itself
     * idempotent (never touches a row whose service_key already
     * exists), so this flag is purely to skip the repeated DB round
     * trips on every page load once seeding has happened once - not a
     * correctness requirement.
     */
    public static function seedServiceCatalogOnce(ServiceRepositoryInterface $repository): void
    {
        if ('1' === get_option(self::CATALOG_SEEDED_OPTION)) {
            return;
        }

        (new ServiceCatalogSeeder($repository))->seed();

        update_option(self::CATALOG_SEEDED_OPTION, '1', false);
    }

    /**
     * Store installation timestamp.
     */
    private function setInstallTimestamp(): void
    {
        if (false === get_option(self::INSTALLED_OPTION)) {
            add_option(self::INSTALLED_OPTION, time(), '', false);
        }
    }

    /**
     * Store current plugin version.
     */
    private function setPluginVersion(): void
    {
        update_option(self::VERSION_OPTION, BIZUPKEEP_CORE_VERSION, false);
    }

    /**
     * Create the plugin's runtime upload directories.
     */
    private function createUploadDirectories(): void
    {
        $uploadDir = wp_upload_dir();

        if (empty($uploadDir['basedir'])) {
            return;
        }

        if (! function_exists('wp_mkdir_p')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        foreach (['', 'documents', 'logs', 'temp', 'exports'] as $subdirectory) {
            $directory = trailingslashit($uploadDir['basedir']) . 'bizupkeep'
                . ($subdirectory === '' ? '' : '/' . $subdirectory);

            if (! file_exists($directory)) {
                wp_mkdir_p($directory);
            }

            $index = trailingslashit($directory) . 'index.php';

            if (! file_exists($index)) {
                file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }
    }
}
