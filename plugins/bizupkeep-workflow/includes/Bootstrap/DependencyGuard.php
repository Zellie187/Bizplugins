<?php

declare(strict_types=1);

namespace BizHub\Workflow\Bootstrap;

/**
 * Verifies that BizUpKeep Workflow's required host plugins are present
 * before any workflow-specific behaviour runs.
 *
 * BizUpKeep Workflow never bypasses the BizHub Framework: it does not
 * build its own database connection, DI container or event dispatcher.
 * If BizHub (the framework) or BizUpKeep Core (the platform's primary
 * plugin) are missing - or BizHub is active but too old to expose the
 * integration surface this plugin needs - it must fail loudly and
 * safely rather than silently degrade or fatal deep inside a request
 * it has no business handling.
 *
 * The plugin header already declares `Requires Plugins: bizhub,
 * bizupkeep-core`, which WordPress 6.5+ enforces at the UI level, but
 * that only checks "is the plugin active", not "is it a compatible
 * version" - so this class is still the authority on both.
 *
 * @package BizHub\Workflow\Bootstrap
 */
final class DependencyGuard
{
    private const NOTICE_OPTION = 'bizupkeep_workflow_dependency_notice';

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
     * version that exposes the `bizhub()` accessor this plugin
     * integrates through - i.e. a version new enough to support
     * external plugins registering into its shared container.
     *
     * A BizHub plugin file can be present (bizhubPluginLoaded() true)
     * while still being an older version that predates this accessor
     * entirely - that combination must be treated as "dependency not
     * satisfied", not "loaded", or this plugin proceeds to register
     * hooks that fatal the moment they run.
     */
    public static function bizhubReady(): bool
    {
        return \function_exists('bizhub') && \bizhub() !== null;
    }

    /**
     * Determine whether the BizUpKeep Core plugin is active.
     */
    public static function coreActive(): bool
    {
        return defined('BIZUPKEEP_CORE_VERSION');
    }

    /**
     * Determine whether every required dependency is present and
     * compatible.
     */
    public static function satisfied(): bool
    {
        return self::bizhubReady() && self::coreActive();
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
                . 'support BizUpKeep Workflow. Update BizHub to a version that provides '
                . 'the bizhub() accessor and the "bizhub/register_providers" / '
                . '"bizhub/container_definitions" hooks.';
        }

        if (! self::coreActive()) {
            $problems[] = 'BizUpKeep Core is not active.';
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
            . esc_html__('BizUpKeep Workflow was deactivated.', 'bizupkeep-workflow')
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

        deactivate_plugins(BIZUPKEEP_WORKFLOW_BASENAME);

        unset($_GET['activate']);
    }
}
