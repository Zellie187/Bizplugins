<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Install;

use BizHub\Security\Authorization\Registries\RoleRegistry;

/**
 * Installs the default BizHub authorization roles.
 */
final class RoleInstaller
{
    public function __construct(private readonly RoleRegistry $roleRegistry)
    {
    }

    public function install(): void
    {
        $this->roleRegistry->register();
    }
}
