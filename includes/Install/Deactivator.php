<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Install;

/**
 * Handles deactivation-time cleanup for BizUpKeep Core.
 *
 * Deliberately does not delete any data - that is reserved for
 * uninstall.php, and only plugin configuration options even then.
 *
 * @package BizUpKeep\Core\Install
 */
final class Deactivator
{
    public function deactivate(): void
    {
        /**
         * Fires immediately before BizUpKeep Core is deactivated.
         *
         * Sibling BizUpKeep modules can use this hook to perform
         * cleanup without deleting persistent data.
         */
        do_action('bizupkeep_core_before_deactivate');

        flush_rewrite_rules();

        /**
         * Fires after BizUpKeep Core has completed deactivation.
         */
        do_action('bizupkeep_core_after_deactivate');
    }
}
