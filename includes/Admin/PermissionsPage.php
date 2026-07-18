<?php

declare(strict_types=1);

namespace BizHub\Admin;

use BizHub\Security\Authorization\Registries\CapabilityRegistry;
use BizHub\Security\Authorization\Support\Roles;

/**
 * Renders the BizHub permissions admin page.
 *
 * @package BizHub\Admin
 */
final class PermissionsPage
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities
    ) {
    }

    /**
     * Render the permissions page.
     */
    public function render(): void
    {
        if (! current_user_can('promote_users')) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('BizHub Permissions', 'bizhub') . '</h1>';

        echo '<h2>' . esc_html__('Registered BizHub Capabilities', 'bizhub') . '</h2>';
        echo '<p>' . esc_html(sprintf(
            /* translators: %d: number of registered capabilities */
            __('%d capabilities are currently registered.', 'bizhub'),
            $this->capabilities->count()
        )) . '</p>';

        echo '<h2>' . esc_html__('Roles', 'bizhub') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Role', 'bizhub') . '</th><th>' . esc_html__('WordPress Capabilities', 'bizhub') . '</th></tr></thead><tbody>';

        foreach (Roles::all() as $roleSlug) {
            $role = get_role($roleSlug);
            $roleCaps = $role !== null ? array_keys(array_filter($role->capabilities)) : [];

            echo '<tr><td>' . esc_html($roleSlug) . '</td><td>' . esc_html(implode(', ', $roleCaps)) . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }
}
