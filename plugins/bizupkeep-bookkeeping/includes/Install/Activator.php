<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Install;

/**
 * Handles activation-time setup for BizUpKeep Bookkeeping.
 *
 * Deliberately does not go through the DI container: activation must
 * work reliably as a standalone, synchronous WordPress hook callback,
 * independent of BizHub's boot lifecycle (BizHub may not have booted
 * yet in the same request - see DependencyGuard).
 *
 * Unlike BizUpKeep Workflow, this plugin never writes files to disk
 * (CSV exports stream straight to the browser), so there are no
 * runtime storage directories to create here.
 *
 * @package BizHub\Bookkeeping\Install
 */
final class Activator
{
    public function activate(): void
    {
        global $wpdb;

        (new Migrator($wpdb, new Schema()))->migrate();
        (new RoleGrant())->install();

        flush_rewrite_rules();
    }
}
