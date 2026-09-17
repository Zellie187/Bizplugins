<?php

declare(strict_types=1);

namespace BizHub\Payments\Bootstrap;

/**
 * Verifies that BizUpKeep Payments' required host plugins are present
 * before any payment-specific behaviour runs.
 *
 * This is the first plugin in the BizUpKeep ecosystem to depend on
 * three siblings at once: it orchestrates across BizUpKeep Core's
 * Service catalog (what is being sold and at what price), BizUpKeep
 * Workflow (advancing a Registration/Amendment/Annual Return instance
 * on payment), and BizUpKeep Bookkeeping (issuing the real Invoice and
 * extending a Bookkeeping Monthly subscription). BizHub itself remains
 * the only framework dependency - this plugin never builds its own DI
 * container, database connection, or event dispatcher.
 *
 * @package BizHub\Payments\Bootstrap
 */
final class DependencyGuard
{
    private const NOTICE_OPTION = 'bizupkeep_payments_dependency_notice';

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Determine whether the BizHub Framework plugin file has been
     * loaded at all.
     */
    public static function bizhubPluginLoaded(): bool
    {
        return defined('BIZHUB_PLUGIN_FILE');
    }

    /**
     * Determine whether BizHub is not just loaded but has booted a
     * version that exposes the `bizhub()` accessor this plugin
     * integrates through.
     */
    public static function bizhubReady(): bool
    {
        return \function_exists('bizhub') && \bizhub() !== null;
    }

    /**
     * Determine whether BizUpKeep Core is active.
     */
    public static function coreActive(): bool
    {
        return defined('BIZUPKEEP_CORE_VERSION');
    }

    /**
     * Determine whether BizUpKeep Workflow is active.
     */
    public static function workflowActive(): bool
    {
        return defined('BIZUPKEEP_WORKFLOW_VERSION');
    }

    /**
     * Determine whether BizUpKeep Bookkeeping is active.
     */
    public static function bookkeepingActive(): bool
    {
        return defined('BIZUPKEEP_BOOKKEEPING_VERSION');
    }

    /**
     * Determine whether every required dependency is present and
     * compatible.
     */
    public static function satisfied(): bool
    {
        return self::bizhubReady()
            && self::coreActive()
            && self::workflowActive()
            && self::bookkeepingActive();
    }

    /**
     * Check dependencies and, if any are missing or incompatible,
     * register an admin notice explaining what is wrong and
     * deactivate this plugin so it does not run in a broken,
     * half-integrated state.
     */
    public static function checkAndNotify(): void
    {
        if (self::satisfied()) {
            delete_option(self::NOTICE_OPTION);

            return;
        }

        $problems = [];

        if (! self::bizhubPluginLoaded()) {
            $problems[] = 'BizHub is not active.';
        } elseif (! self::bizhubReady()) {
            $problems[] = 'The active BizHub plugin is an older version that does not '
                . 'support BizUpKeep Payments. Update BizHub to a version that provides '
                . 'the bizhub() accessor and the "bizhub/register_providers" / '
                . '"bizhub/container_definitions" hooks.';
        }

        if (! self::coreActive()) {
            $problems[] = 'BizUpKeep Core is not active.';
        }

        if (! self::workflowActive()) {
            $problems[] = 'BizUpKeep Workflow is not active.';
        }

        if (! self::bookkeepingActive()) {
            $problems[] = 'BizUpKeep Bookkeeping is not active.';
        }

        update_option(self::NOTICE_OPTION, $problems, false);

        add_action('admin_notices', [self::class, 'renderNotice']);
        add_action('admin_init', [self::class, 'deactivateSelf']);
    }

    /**
     * Render the missing/incompatible-dependency admin notice.
     */
    public static function renderNotice(): void
    {
        $problems = get_option(self::NOTICE_OPTION, []);

        if (! \is_array($problems) || $problems === []) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>'
            . esc_html__('BizUpKeep Payments was deactivated.', 'bizupkeep-payments')
            . '</strong></p><ul>';

        foreach ($problems as $problem) {
            echo '<li>' . esc_html((string) $problem) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * Deactivate this plugin from within an admin request.
     */
    public static function deactivateSelf(): void
    {
        if (! \function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins(BIZUPKEEP_PAYMENTS_BASENAME);

        unset($_GET['activate']);
    }
}
