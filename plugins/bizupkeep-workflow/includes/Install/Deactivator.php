<?php

declare(strict_types=1);

namespace BizHub\Workflow\Install;

/**
 * Handles deactivation-time cleanup for BizUpKeep Workflow.
 *
 * Deliberately does not touch the database or delete any workflow
 * data - that is reserved for uninstall.php, and only when the user
 * has opted in to deleting their data.
 *
 * @package BizHub\Workflow\Install
 */
final class Deactivator
{
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
