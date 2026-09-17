<?php

declare(strict_types=1);

namespace BizHub\Payments\Install;

/**
 * Handles deactivation-time cleanup for BizUpKeep Payments.
 *
 * Deliberately does not touch the database or delete any payment
 * attempt data - that is reserved for uninstall.php, and only when
 * the user has opted in to deleting their data.
 *
 * @package BizHub\Payments\Install
 */
final class Deactivator
{
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
