<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Install;

/**
 * Handles activation-time setup for BizUpKeep Core.
 *
 * Deliberately does not go through the DI container: activation must
 * work reliably as a standalone, synchronous WordPress hook callback,
 * independent of BizHub's boot lifecycle (BizHub may not have booted
 * yet in the same request - see Bootstrap\DependencyGuard).
 *
 * @package BizUpKeep\Core\Install
 */
final class Activator
{
    private const VERSION_OPTION = 'bizupkeep_core_version';
    private const INSTALLED_OPTION = 'bizupkeep_core_installed';

    public function activate(): void
    {
        $this->setInstallTimestamp();
        $this->setPluginVersion();
        $this->createUploadDirectories();

        flush_rewrite_rules();
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
