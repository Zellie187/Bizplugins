<?php

declare(strict_types=1);

namespace BizHub\Stub\Install;

use WP_Role;

/**
 * Grants BizUpKeep Stub's capabilities to roles, as configured in
 * config/permissions.php.
 *
 * Runs at activation, independent of the DI container, for the same
 * reason Activator does.
 *
 * @package BizHub\Stub\Install
 */
final class RoleGrant
{
    public function install(): void
    {
        /** @var array<string,array<int,string>> $grants */
        $grants = require BIZUPKEEP_STUB_PATH . 'config/permissions.php';

        foreach ($grants as $roleName => $capabilities) {
            $role = get_role($roleName);

            if (! $role instanceof WP_Role) {
                continue;
            }

            foreach ($capabilities as $capability) {
                $role->add_cap($capability);
            }
        }
    }
}
