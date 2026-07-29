<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Admin\Providers;

use BizHub\Bookkeeping\Admin\InternalBooksPage;
use BizHub\Bookkeeping\Admin\ManualJournalEntryPage;
use BizHub\Bookkeeping\Admin\SubscriptionOverviewPage;
use BizHub\Bookkeeping\Policies\Capabilities;
use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;

/**
 * The one provider in this plugin that always performs real boot-time
 * wiring, regardless of which other vertical providers exist -
 * capability registration lives here (not scattered across the mostly
 * empty AccountsServiceProvider/LedgerServiceProvider/
 * ReportingServiceProvider) plus the single admin screen this plugin
 * has.
 *
 * @package BizHub\Bookkeeping\Admin\Providers
 */
final class BookkeepingAdminServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly ManualJournalEntryPage $page,
        private readonly SubscriptionOverviewPage $subscriptionOverviewPage,
        private readonly InternalBooksPage $internalBooksPage,
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
        foreach (Capabilities::all() as $capability) {
            $this->authorization->registerCapability($capability);
        }

        // Registered regardless of is_admin() - handleExport() must be
        // reachable from the admin-post.php dispatcher, which is not
        // itself an admin-menu page render.
        add_action('admin_post_' . InternalBooksPage::EXPORT_ACTION, [$this->internalBooksPage, 'handleExport']);

        if (! is_admin()) {
            return;
        }

        // Priority 20: safely after BizHub's own top-level "bizhub" menu
        // registers (default priority 10) - registering a submenu before
        // its parent top-level page exists corrupts the WP admin menu
        // rather than simply failing, a bug already hit once elsewhere
        // in this codebase.
        add_action('admin_menu', [$this, 'addMenuPage'], 20);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'bizhub',
            __('Bookkeeping', 'bizupkeep-bookkeeping'),
            __('Bookkeeping', 'bizupkeep-bookkeeping'),
            Capabilities::BOOKKEEPING_MANAGE,
            ManualJournalEntryPage::SLUG,
            [$this->page, 'render']
        );

        add_submenu_page(
            'bizhub',
            __('Bookkeeping Subscriptions', 'bizupkeep-bookkeeping'),
            __('BK Subscriptions', 'bizupkeep-bookkeeping'),
            Capabilities::BOOKKEEPING_MANAGE,
            SubscriptionOverviewPage::SLUG,
            [$this->subscriptionOverviewPage, 'render']
        );

        add_submenu_page(
            'bizhub',
            __('Internal Books', 'bizupkeep-bookkeeping'),
            __('Internal Books', 'bizupkeep-bookkeeping'),
            Capabilities::BOOKKEEPING_MANAGE,
            InternalBooksPage::SLUG,
            [$this->internalBooksPage, 'render']
        );
    }
}
