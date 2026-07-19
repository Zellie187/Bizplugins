<?php

declare(strict_types=1);

namespace BizHub\Workflow\Policies;

/**
 * Defines every capability introduced by BizUpKeep Workflow.
 *
 * Modules should reference these constants instead of hardcoded
 * capability strings, and always perform checks through BizHub's
 * AuthorizationServiceInterface rather than calling WordPress
 * capability functions directly.
 *
 * @package BizHub\Workflow\Policies
 */
final class Capabilities
{
    public const WORKFLOW_VIEW = 'workflow.view';

    public const WORKFLOW_MANAGE = 'workflow.manage';

    public const WORKFLOW_TRANSITION = 'workflow.transition';

    /**
     * Return every capability introduced by this plugin.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::WORKFLOW_VIEW,
            self::WORKFLOW_MANAGE,
            self::WORKFLOW_TRANSITION,
        ];
    }

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }
}
