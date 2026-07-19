<?php

declare(strict_types=1);

namespace BizHub\Framework\Install\Providers;

use BizHub\Framework\Install\Installer;
use BizHub\Framework\Install\Migrator;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * Install Service Provider.
 *
 * WordPress only fires register_activation_hook() when a plugin is
 * deactivated and reactivated, not on a regular in-place "Update Now".
 * This provider catches that gap: on every normal boot, it checks
 * whether the installed schema version matches the code's current
 * version and re-runs the installer if not, so a future release's
 * schema changes still get applied after a plain update.
 *
 * @package BizHub\Framework\Install\Providers
 */
final class InstallServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly Migrator $migrator,
        private readonly Installer $installer
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        if ($this->migrator->needsMigration()) {
            $this->installer->install();
        }
    }
}
