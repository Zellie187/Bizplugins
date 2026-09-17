<?php

declare(strict_types=1);

namespace BizHub\Stub\Policies;

/**
 * Defines every capability introduced by BizUpKeep Stub.
 *
 * Modules should reference these constants instead of hardcoded
 * capability strings, and always perform checks through BizHub's
 * AuthorizationServiceInterface rather than calling WordPress
 * capability functions directly.
 *
 * @package BizHub\Stub\Policies
 */
final class Capabilities
{
    /** Viewing any client's books on the staff dashboard, editing Stub API settings, and running migrations. */
    public const MANAGE_STUB = 'bizupkeep_stub.manage_stub';

    /**
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::MANAGE_STUB,
        ];
    }

    private function __construct()
    {
    }
}
