<?php

declare(strict_types=1);

namespace BizHub\Payments\Install;

use WP_Role;

/**
 * Grants BizUpKeep Payments' capabilities to roles, as configured in
 * config/permissions.php.
 *
 * Runs at activation, independent of the DI container, for the same
 * reason Activator does: activation must work as a standalone,
 * synchronous WordPress hook callback.
 *
 * @package BizHub\Payments\Install
 */
final class RoleGrant
{
    /**
     * Grant every configured BizUpKeep Payments capability to its
     * configured roles, where those roles exist.
     */
    public function install(): void
    {
        /** @var array<string,array<int,string>> $grants */
        $grants = require BIZUPKEEP_PAYMENTS_PATH . 'config/permissions.php';

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
