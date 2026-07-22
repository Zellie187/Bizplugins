<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Workflow\Admin\WorkflowDashboardPage;
use BizHub\Workflow\Policies\Capabilities;

/**
 * Registers the Workflow Dashboard screen as a submenu page under
 * BizHub's existing top-level "bizhub" wp-admin menu, following the
 * same pattern as QualityReviewAdminServiceProvider.
 *
 * @package BizHub\Workflow\Admin\Providers
 */
final class WorkflowDashboardAdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly WorkflowDashboardPage $page
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        if (! is_admin()) {
            return;
        }

        /*
         * Priority 15 - after BizHub's own AdminMenu::buildMenu() (default
         * priority 10) registers the top-level "bizhub" page, and after
         * WorkflowAdminMenu's default-priority (10) "Workflows", but
         * before QualityReviewAdminServiceProvider's priority-20 "Quality
         * Review". Registering a submenu before its parent top-level menu
         * exists doesn't queue it - WordPress silently promotes it into a
         * second, broken top-level menu instead, which is what priority 5
         * did here originally.
         */
        add_action('admin_menu', [$this, 'addMenuPage'], 15);
    }

    /**
     * Add the "Dashboard" submenu page.
     */
    public function addMenuPage(): void
    {
        $label = __('Workflow Dashboard', 'bizupkeep-workflow');

        add_submenu_page(
            'bizhub',
            $label,
            $label,
            Capabilities::WORKFLOW_VIEW,
            WorkflowDashboardPage::SLUG,
            [$this->page, 'render']
        );
    }
}
