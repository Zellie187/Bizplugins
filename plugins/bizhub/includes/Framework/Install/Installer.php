<?php

declare(strict_types=1);

namespace BizHub\Framework\Install;

use BizHub\Security\Authorization\Install\RoleInstaller;

/**
 * Orchestrates first-run and every-activation setup: database schema
 * and BizHub roles/capabilities.
 *
 * @package BizHub\Framework\Install
 */
final class Installer
{
    public function __construct(
        private readonly Migrator $migrator,
        private readonly RoleInstaller $roleInstaller
    ) {
    }

    public function install(): void
    {
        $this->migrator->migrate();
        $this->roleInstaller->install();
    }
}
