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

        $settingsLabel = __('Settings', 'bizhub');
        add_submenu_page(
            'bizhub',
            $settingsLabel,
            $settingsLabel,
            $capability,
            'bizhub',
            [$this->settingsPage, 'render']
        );

        $toolsLabel = __('Tools', 'bizhub');
        add_submenu_page('bizhub', $toolsLabel, $toolsLabel, $capability, 'bizhub-tools', [$this->toolsPage, 'render']);

        $statusLabel = __('System Status', 'bizhub');
        add_submenu_page(
            'bizhub',
            $statusLabel,
            $statusLabel,
            $capability,
            'bizhub-status',
            [$this->systemStatusPage, 'render']
        );

        $logsLabel = __('Logs', 'bizhub');
        add_submenu_page('bizhub', $logsLabel, $logsLabel, $capability, 'bizhub-logs', [$this->logsPage, 'render']);

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
