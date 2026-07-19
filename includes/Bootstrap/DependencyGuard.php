<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Bootstrap;

/**
 * Verifies that BizUpKeep Core's required host plugin (BizHub) is
 * present before any Core behaviour that touches BizHub's shared
 * container runs.
 *
 * BizUpKeep Core is the platform's primary plugin, built on top of the
 * BizHub Framework: it does not build its own DI container, database
 * connection or event dispatcher - it contributes into BizHub's shared
 * ones via the same 'bizhub/container_definitions' and
 * 'bizhub/register_providers' extension points BizUpKeep Workflow
 * uses. If BizHub is missing, or active but too old to expose that
 * integration surface, Core must fail loudly and safely rather than
 * silently degrade or fatal deep inside a request.
 *
 * @package BizUpKeep\Core\Bootstrap
 */
final class DependencyGuard
{
    private const NOTICE_OPTION = 'bizupkeep_core_dependency_notice';

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Determine whether the BizHub Framework plugin file has been
     * loaded at all. True as soon as bizhub.php has been included,
     * regardless of which version it is.
     */
    public static function bizhubPluginLoaded(): bool
    {
        return defined('BIZHUB_PLUGIN_FILE');
    }

    /**
     * Determine whether BizHub is not just loaded but has booted a
     * version that exposes the `bizhub()` accessor Core integrates
     * through - i.e. a version new enough to support external plugins
     * registering into its shared container.
     */
    public static function bizhubReady(): bool
    {
        return \function_exists('bizhub') && \bizhub() !== null;
    }

    /**
     * Determine whether every required dependency is present and
     * compatible.
     */
    public static function satisfied(): bool
    {
        return self::bizhubReady();
    }

    /**
     * Check dependencies and, if missing or incompatible, register an
     * admin notice explaining what is wrong and deactivate this plugin
     * so it does not run in a broken, half-integrated state.
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
                . 'support BizUpKeep Core. Update BizHub to a version that provides '
                . 'the bizhub() accessor and the "bizhub/register_providers" / '
                . '"bizhub/container_definitions" hooks.';
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
            . esc_html__('BizUpKeep Core was deactivated.', 'bizupkeep-core')
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

        deactivate_plugins(BIZUPKEEP_CORE_BASENAME);

        unset($_GET['activate']);
    }
}
