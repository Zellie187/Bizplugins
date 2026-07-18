<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Support;

/**
 * BizHub
 *
 * Enterprise Business Management Platform
 *
 * Defines every BizHub role.
 *
 * These role identifiers are used throughout the
 * application and mapped to WordPress roles during
 * registration.
 *
 * @package BizHub
 * @subpackage Security\Authorization
 * @since 0.2.0
 */
final class Roles
{
    /**
     * BizHub Administrator.
     */
    public const ADMINISTRATOR = 'bizhub_administrator';

    /**
     * BizHub Manager.
     */
    public const MANAGER = 'bizhub_manager';

    /**
     * BizHub Staff.
     */
    public const STAFF = 'bizhub_staff';

    /**
     * BizHub Client.
     */
    public const CLIENT = 'bizhub_client';

    /**
     * Return all BizHub roles.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::ADMINISTRATOR,
            self::MANAGER,
            self::STAFF,
            self::CLIENT,
        ];
    }

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }
}
