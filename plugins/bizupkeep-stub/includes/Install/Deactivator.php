<?php

declare(strict_types=1);

namespace BizHub\Stub\Install;

/**
 * Handles deactivation-time cleanup for BizUpKeep Stub.
 *
 * Deliberately does not touch the database or delete any provisioned-
 * business data - that is reserved for uninstall.php, and only when
 * the user has opted in to deleting their data.
 *
 * @package BizHub\Stub\Install
 */
final class Deactivator
{
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
