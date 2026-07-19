<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Registries;

use BizHub\Security\Authorization\Support\Roles;

/**
 * BizHub
 *
 * Enterprise Business Management Platform
 *
 * Responsible for registering and synchronizing
 * BizHub roles with the WordPress role system.
 *
 * @package BizHub
 * @subpackage Security\Authorization
 * @since 0.2.0
 */
final class RoleRegistry
{
    /**
     * Register all BizHub roles.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerAdministrator();
        $this->registerManager();
        $this->registerStaff();
        $this->registerClient();
    }

    /**
     * Register the BizHub Administrator role.
     *
     * @return void
     */
    private function registerAdministrator(): void
    {
        if (get_role(Roles::ADMINISTRATOR) !== null) {
            return;
        }

        add_role(
            Roles::ADMINISTRATOR,
            __('BizHub Administrator', 'bizhub'),
            [
                'read' => true,
                'manage_options' => true,
            ]
        );
    }

    /**
     * Register the BizHub Manager role.
     *
     * @return void
     */
    private function registerManager(): void
    {
        if (get_role(Roles::MANAGER) !== null) {
            return;
        }

        add_role(
            Roles::MANAGER,
            __('BizHub Manager', 'bizhub'),
            [
                'read' => true,
            ]
        );
    }

    /**
     * Register the BizHub Staff role.
     *
     * @return void
     */
    private function registerStaff(): void
    {
        if (get_role(Roles::STAFF) !== null) {
            return;
        }

        add_role(
            Roles::STAFF,
            __('BizHub Staff', 'bizhub'),
            [
                'read' => true,
            ]
        );
    }

    /**
     * Register the BizHub Client role.
     *
     * @return void
     */
    private function registerClient(): void
    {
        if (get_role(Roles::CLIENT) !== null) {
            return;
        }

        add_role(
            Roles::CLIENT,
            __('BizHub Client', 'bizhub'),
            [
                'read' => true,
            ]
        );
    }
}
