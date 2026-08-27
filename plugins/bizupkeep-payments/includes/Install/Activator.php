<?php

declare(strict_types=1);

namespace BizHub\Payments\Install;

/**
 * Handles activation-time setup for BizUpKeep Payments.
 *
 * Deliberately does not go through the DI container: activation must
 * work reliably as a standalone, synchronous WordPress hook callback,
 * independent of BizHub's boot lifecycle (BizHub may not have booted
 * yet in the same request - see Bootstrap\DependencyGuard).
 *
 * @package BizHub\Payments\Install
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
