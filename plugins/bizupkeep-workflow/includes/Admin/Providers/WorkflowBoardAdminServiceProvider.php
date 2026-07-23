<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Workflow\Admin\WorkflowBoardPage;
use BizHub\Workflow\Policies\Capabilities;

/**
 * Registers the Workflows Board screen as a submenu page under
 * BizHub's existing top-level "bizhub" wp-admin menu, following the
 * same pattern as QualityReviewAdminServiceProvider/
 * WorkflowDashboardAdminServiceProvider.
 *
 * @package BizHub\Workflow\Admin\Providers
 */
final class WorkflowBoardAdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly WorkflowBoardPage $page
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
         * Priority 25 - after the top-level "bizhub" menu (priority 10),
         * "Workflows" (10), "Workflow Dashboard" (15), and "Quality
         * Review" (20) all register. See
         * WorkflowDashboardAdminServiceProvider's own comment for why a
         * submenu's priority must never be lower than whatever registers
         * its parent top-level page - registering too early doesn't
         * queue the submenu, it corrupts the menu into a second, broken
         * top-level entry.
         */
        add_action('admin_menu', [$this, 'addMenuPage'], 25);
    }

    /**
     * Add the "Workflows Board" submenu page.
     */
    public function addMenuPage(): void
    {
        $label = __('Workflows Board', 'bizupkeep-workflow');

        add_submenu_page(
            'bizhub',
            $label,
            $label,
            Capabilities::WORKFLOW_VIEW,
            WorkflowBoardPage::SLUG,
            [$this->page, 'render']
        );
    }
}
