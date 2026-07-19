<?php

declare(strict_types=1);

namespace BizHub\Workflow\Install;

use WP_Role;

/**
 * Grants BizUpKeep Workflow's capabilities to roles, as configured in
 * config/permissions.php.
 *
 * Runs at activation, independent of the DI container, for the same
 * reason BizHub\Framework\Install\Activator does: activation must work
 * as a standalone, synchronous WordPress hook callback.
 *
 * Custom capability strings (e.g. "workflow.manage") are not native
 * WordPress capabilities, so they must be explicitly granted to roles
 * before BizHub's AuthorizationServiceInterface::can() can meaningfully
 * evaluate them via WordPress's own user_can().
 *
 * @package BizHub\Workflow\Install
 */
final class RoleGrant
{
    /**
     * Grant every configured BizUpKeep Workflow capability to its
     * configured roles, where those roles exist.
     */
    public function install(): void
    {
        /** @var array<string,array<int,string>> $grants */
        $grants = require BIZUPKEEP_WORKFLOW_CONFIG_PATH . 'permissions.php';

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
