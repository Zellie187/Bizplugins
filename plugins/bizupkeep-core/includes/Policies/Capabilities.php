<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Policies;

/**
 * Defines every capability introduced by BizUpKeep Core.
 *
 * Modules should reference these constants instead of hardcoded
 * capability strings, and always perform checks through BizHub's
 * AuthorizationServiceInterface rather than calling WordPress
 * capability functions directly.
 *
 * @package BizUpKeep\Core\Policies
 */
final class Capabilities
{
    /** Editing the Service catalog's staff-editable fields (VAT treatment, recurring flag, notes, active). */
    public const MANAGE_SERVICES = 'bizupkeep_core.manage_services';

    /**
     * Return every capability introduced by this plugin.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::MANAGE_SERVICES,
        ];
    }

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }
}
