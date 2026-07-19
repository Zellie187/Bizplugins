<?php

declare(strict_types=1);

namespace BizHub\Workflow\Install;

/**
 * Handles activation-time setup for BizUpKeep Workflow.
 *
 * Deliberately does not go through the DI container: activation must
 * work reliably as a standalone, synchronous WordPress hook callback,
 * independent of BizHub's boot lifecycle (BizHub may not have booted
 * yet in the same request - see DependencyGuard).
 *
 * @package BizHub\Workflow\Install
 */
final class Activator
{
    public function activate(): void
    {
        global $wpdb;

        (new Migrator($wpdb, new Schema()))->migrate();
        (new RoleGrant())->install();

        $this->createStorageDirectories();

        flush_rewrite_rules();
    }

    /**
     * Create the plugin's runtime storage directories.
     */
    private function createStorageDirectories(): void
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        foreach (['cache', 'logs', 'sessions', 'workflow'] as $directory) {
            $path = BIZUPKEEP_WORKFLOW_STORAGE_PATH . $directory;

            if (! file_exists($path)) {
                wp_mkdir_p($path);
            }

            $index = trailingslashit($path) . 'index.php';

            if (! file_exists($index)) {
                file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }
    }
}
