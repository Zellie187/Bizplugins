<?php

declare(strict_types=1);

namespace BizHub\Platform\Authorization\Support;

/**
 * Maps BizHub capabilities to native WordPress capabilities.
 *
 * This provides an abstraction layer between BizHub and
 * the WordPress permission system.
 *
 * @package BizHub\Platform\Authorization\Support
 */
final class WordPressCapabilityMapper
{
    /**
     * Capability mappings.
     *
     * @var array<string,string>
     */
    private array $map = [
        Capabilities::COMPANY_VIEW        => 'read',
        Capabilities::COMPANY_CREATE      => 'edit_posts',
        Capabilities::COMPANY_EDIT        => 'edit_posts',
        Capabilities::COMPANY_DELETE      => 'delete_posts',

        Capabilities::APPLICATION_VIEW    => 'read',
        Capabilities::APPLICATION_CREATE  => 'edit_posts',
        Capabilities::APPLICATION_EDIT    => 'edit_posts',
        Capabilities::APPLICATION_APPROVE => 'publish_posts',

        Capabilities::DOCUMENT_VIEW       => 'read',
        Capabilities::DOCUMENT_UPLOAD     => 'upload_files',
        Capabilities::DOCUMENT_DOWNLOAD   => 'read',
        Capabilities::DOCUMENT_DELETE     => 'delete_posts',

        Capabilities::PORTAL_ACCESS       => 'read',

        Capabilities::WORKFLOW_VIEW       => 'read',
        Capabilities::WORKFLOW_MANAGE     => 'manage_options',

        Capabilities::SETTINGS_MANAGE     => 'manage_options',
        Capabilities::USERS_MANAGE        => 'list_users',
    ];

    /**
     * Resolve the WordPress capability.
     */
    public function resolve(string $capability): string
    {
        return $this->map[$capability] ?? $capability;
    }

    /**
     * Return all mappings.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        return $this->map;
    }
}
