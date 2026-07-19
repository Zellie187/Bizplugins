<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Support;

/**
 * BizHub
 *
 * Enterprise Business Management Platform
 *
 * Maps BizHub business capabilities to native
 * WordPress capabilities.
 *
 * This is the only class that should know about
 * WordPress capability names.
 *
 * @package BizHub
 * @subpackage Security\Authorization
 * @since 0.2.0
 */
final class WordPressCapabilityMapper
{
    /**
     * Capability map.
     *
     * @var array<string, string>
     */
    private const MAP = [

        /*
        |--------------------------------------------------------------------------
        | Companies
        |--------------------------------------------------------------------------
        */

        Capabilities::COMPANY_VIEW => 'read',

        Capabilities::COMPANY_CREATE => 'edit_posts',

        Capabilities::COMPANY_EDIT => 'edit_posts',

        Capabilities::COMPANY_DELETE => 'delete_posts',

        /*
        |--------------------------------------------------------------------------
        | Applications
        |--------------------------------------------------------------------------
        */

        Capabilities::APPLICATION_VIEW => 'read',

        Capabilities::APPLICATION_CREATE => 'edit_posts',

        Capabilities::APPLICATION_EDIT => 'edit_posts',

        Capabilities::APPLICATION_SUBMIT => 'edit_posts',

        Capabilities::APPLICATION_CANCEL => 'edit_posts',

        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */

        Capabilities::DOCUMENT_VIEW => 'read',

        Capabilities::DOCUMENT_UPLOAD => 'upload_files',

        Capabilities::DOCUMENT_DOWNLOAD => 'read',

        Capabilities::DOCUMENT_DELETE => 'delete_posts',

        /*
        |--------------------------------------------------------------------------
        | Client Portal
        |--------------------------------------------------------------------------
        */

        Capabilities::CLIENT_LOGIN => 'read',

        Capabilities::CLIENT_PROFILE => 'read',

        Capabilities::CLIENT_DOCUMENTS => 'read',

        Capabilities::CLIENT_MESSAGES => 'read',

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Capabilities::REPORT_VIEW => 'read',

        Capabilities::REPORT_EXPORT => 'export',

        /*
        |--------------------------------------------------------------------------
        | Administration
        |--------------------------------------------------------------------------
        */

        Capabilities::ADMIN_SETTINGS => 'manage_options',

        Capabilities::ADMIN_USERS => 'list_users',

        Capabilities::ADMIN_ROLES => 'promote_users',

        Capabilities::ADMIN_AUDIT => 'manage_options',
    ];

    /**
     * Resolve a BizHub capability to its
     * corresponding WordPress capability.
     *
     * @param string $capability BizHub capability.
     *
     * @return string|null
     */
    public function map(string $capability): ?string
    {
        return self::MAP[$capability] ?? null;
    }

    /**
     * Determine whether a BizHub capability
     * has a registered mapping.
     *
     * @param string $capability BizHub capability.
     *
     * @return bool
     */
    public function has(string $capability): bool
    {
        return isset(self::MAP[$capability]);
    }

    /**
     * Return the complete capability map.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return self::MAP;
    }
}
