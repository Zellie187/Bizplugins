<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Workflow\Admin\QualityReviewPage;
use BizHub\Workflow\Policies\Capabilities;

/**
 * Registers the Quality Review screen as a submenu page under BizHub's
 * existing top-level "bizhub" wp-admin menu (registered by
 * BizHub\Admin\AdminMenu), rather than standing up a separate menu or
 * front-end staff portal - staff already do every other BizHub-related
 * task from wp-admin.
 *
 * @package BizHub\Workflow\Admin\Providers
 */
final class QualityReviewAdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly QualityReviewPage $page
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
         * Registered on the generic 'admin_menu' hook (priority 20, after
         * BizHub's own AdminMenu default-priority callback) so the
         * top-level "bizhub" menu already exists when add_submenu_page()
         * runs.
         */
        add_action('admin_menu', [$this, 'addMenuPage'], 20);
    }

    /**
     * Add the "Quality Review" submenu page.
     */
    public function addMenuPage(): void
    {
        $label = __('Quality Review', 'bizupkeep-workflow');

        add_submenu_page(
            'bizhub',
            $label,
            $label,
            Capabilities::WORKFLOW_VIEW,
            QualityReviewPage::SLUG,
            [$this->page, 'render']
        );
    }
}
