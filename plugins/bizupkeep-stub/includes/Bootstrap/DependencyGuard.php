<?php

declare(strict_types=1);

namespace BizHub\Stub\Bootstrap;

/**
 * Verifies that BizUpKeep Stub's required host plugins are present
 * before any Stub-specific behaviour runs.
 *
 * Depends on BizHub (shared container) and BizUpKeep Bookkeeping (for
 * Company/CompanySettings data and the fixed InternalCompanyProviderInterface
 * binding) - not on BizUpKeep Workflow or BizUpKeep Payments, since Stub
 * provisioning/migration only ever needs a Company, not an in-flight
 * workflow or payment.
 *
 * @package BizHub\Stub\Bootstrap
 */
final class DependencyGuard
{
    private const NOTICE_OPTION = 'bizupkeep_stub_dependency_notice';

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    public static function bizhubPluginLoaded(): bool
    {
        return defined('BIZHUB_PLUGIN_FILE');
    }

    public static function bizhubReady(): bool
    {
        return \function_exists('bizhub') && \bizhub() !== null;
    }

    public static function coreActive(): bool
    {
        return defined('BIZUPKEEP_CORE_VERSION');
    }

    public static function bookkeepingActive(): bool
    {
        return defined('BIZUPKEEP_BOOKKEEPING_VERSION');
    }

    public static function satisfied(): bool
    {
        return self::bizhubReady()
            && self::coreActive()
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
                . 'support BizUpKeep Stub. Update BizHub to a version that provides '
                . 'the bizhub() accessor and the "bizhub/register_providers" / '
                . '"bizhub/container_definitions" hooks.';
        }

        if (! self::coreActive()) {
            $problems[] = 'BizUpKeep Core is not active.';
        }

        if (! self::bookkeepingActive()) {
            $problems[] = 'BizUpKeep Bookkeeping is not active.';
        }

        update_option(self::NOTICE_OPTION, $problems, false);

        add_action('admin_notices', [self::class, 'renderNotice']);
        add_action('admin_init', [self::class, 'deactivateSelf']);
    }

    public static function renderNotice(): void
    {
        $problems = get_option(self::NOTICE_OPTION, []);

        if (! \is_array($problems) || $problems === []) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>'
            . esc_html__('BizUpKeep Stub was deactivated.', 'bizupkeep-stub')
            . '</strong></p><ul>';

        foreach ($problems as $problem) {
            echo '<li>' . esc_html((string) $problem) . '</li>';
        }

        echo '</ul></div>';
    }

    public static function deactivateSelf(): void
    {
        if (! \function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins(BIZUPKEEP_STUB_BASENAME);

        unset($_GET['activate']);
    }
}
