<?php

declare(strict_types=1);

namespace BizHub\Framework\Install;

use BizHub\Security\Authorization\Install\RoleInstaller;
use BizHub\Security\Authorization\Registries\RoleRegistry;

/**
 * Handles activation-time framework setup.
 *
 * Deliberately does not go through the DI container: activation must
 * work reliably as a standalone, synchronous WordPress hook callback,
 * independent of the rest of the application's boot lifecycle.
 *
 * @package BizHub\Framework\Install
 */
final class Activator
{
    public function activate(): void
    {
        global $wpdb;

        $installer = new Installer(
            new Migrator($wpdb, new Schema()),
            new RoleInstaller(new RoleRegistry())
        );

        $installer->install();
    }
}
