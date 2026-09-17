<?php

declare(strict_types=1);

namespace BizHub\Payments\Policies;

/**
 * Defines every capability introduced by BizUpKeep Payments.
 *
 * Modules should reference these constants instead of hardcoded
 * capability strings, and always perform checks through BizHub's
 * AuthorizationServiceInterface rather than calling WordPress
 * capability functions directly.
 *
 * @package BizHub\Payments\Policies
 */
final class Capabilities
{
    /** Viewing/retrying payment attempts and editing gateway settings. */
    public const MANAGE_PAYMENTS = 'bizupkeep_payments.manage_payments';

    /**
     * Return every capability introduced by this plugin.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::MANAGE_PAYMENTS,
        ];
    }

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }
}
