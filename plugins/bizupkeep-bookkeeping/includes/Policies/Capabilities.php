<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Policies;

/**
 * Defines every capability introduced by BizUpKeep Bookkeeping.
 *
 * Modules should reference these constants instead of hardcoded
 * capability strings, and always perform checks through BizHub's
 * AuthorizationServiceInterface rather than calling WordPress
 * capability functions directly.
 *
 * @package BizHub\Bookkeeping\Policies
 */
final class Capabilities
{
    public const BOOKKEEPING_VIEW = 'bookkeeping.view';

    public const BOOKKEEPING_CAPTURE = 'bookkeeping.capture';

    /** Custom chart-of-accounts edits, manual multi-line journal entries, reversals. */
    public const BOOKKEEPING_MANAGE = 'bookkeeping.manage';

    public const BOOKKEEPING_EXPORT = 'bookkeeping.export';

    /**
     * Return every capability introduced by this plugin.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::BOOKKEEPING_VIEW,
            self::BOOKKEEPING_CAPTURE,
            self::BOOKKEEPING_MANAGE,
            self::BOOKKEEPING_EXPORT,
        ];
    }

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }
}
