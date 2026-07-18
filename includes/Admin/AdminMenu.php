<?php

declare(strict_types=1);

namespace BizHub\Admin;

use BizHub\Security\Authorization\Support\Capabilities;
use BizHub\Security\Authorization\Support\WordPressCapabilityMapper;

/**
 * Registers the BizHub admin menu and its submenu pages.
 *
 * @package BizHub\Admin
 */
final class AdminMenu
{
    public function __construct(
        private readonly SettingsPage $settingsPage,
        private readonly ToolsPage $toolsPage,
        private readonly SystemStatusPage $systemStatusPage,
        private readonly LogsPage $logsPage,
        private readonly PermissionsPage $permissionsPage,
        private readonly WordPressCapabilityMapper $capabilityMapper
    ) {
    }

    /**
     * Register the 'admin_menu' hook.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'buildMenu']);
    }

    /**
     * Build the admin menu structure.
     */
    public function buildMenu(): void
    {
        $capability = $this->wpCapability(Capabilities::ADMIN_SETTINGS);

        add_menu_page(
            __('BizHub', 'bizhub'),
            __('BizHub', 'bizhub'),
            $capability,
            'bizhub',
            [$this->settingsPage, 'render'],
            'dashicons-businessman',
            56
        );

        add_submenu_page('bizhub', __('Settings', 'bizhub'), __('Settings', 'bizhub'), $capability, 'bizhub', [$this->settingsPage, 'render']);
        add_submenu_page('bizhub', __('Tools', 'bizhub'), __('Tools', 'bizhub'), $capability, 'bizhub-tools', [$this->toolsPage, 'render']);
        add_submenu_page('bizhub', __('System Status', 'bizhub'), __('System Status', 'bizhub'), $capability, 'bizhub-status', [$this->systemStatusPage, 'render']);
        add_submenu_page('bizhub', __('Logs', 'bizhub'), __('Logs', 'bizhub'), $capability, 'bizhub-logs', [$this->logsPage, 'render']);

        add_submenu_page(
            'bizhub',
            __('Permissions', 'bizhub'),
            __('Permissions', 'bizhub'),
            $this->wpCapability(Capabilities::ADMIN_ROLES),
            'bizhub-permissions',
            [$this->permissionsPage, 'render']
        );
    }

    /**
     * Resolve a BizHub capability to its native WordPress capability.
     */
    private function wpCapability(string $capability): string
    {
        return $this->capabilityMapper->map($capability) ?? 'manage_options';
    }
}
