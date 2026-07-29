<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Admin;

use BizHub\Bookkeeping\Contracts\SubscriptionRepositoryInterface;
use BizHub\Bookkeeping\Entities\Subscription;
use BizHub\Bookkeeping\Policies\Capabilities;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Staff-facing, read-only overview of every company's bookkeeping
 * subscription, sorted soonest-expiry-first, so renewals can be chased
 * proactively rather than left entirely to the automated reminder
 * email (SubscriptionReminderService) or discovered by accident.
 *
 * Deliberately does not duplicate ManualJournalEntryPage's
 * Extend/Suspend/Reactivate mutations - this page only links into it
 * (pre-filled with the company UUID) for that.
 *
 * @package BizHub\Bookkeeping\Admin
 */
final class SubscriptionOverviewPage
{
    public const SLUG = 'bizupkeep-bookkeeping-subscriptions';

    /**
     * Matches SubscriptionReminderService's own window, so a company
     * flagged "Expiring Soon" here is exactly one this round's
     * automated reminder has also (or is about to have) emailed about.
     */
    private const EXPIRING_SOON_WINDOW_DAYS = 3;

    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly CompanyServiceInterface $companies,
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    /**
     * Render the page. Registered as the admin_menu callback for
     * self::SLUG.
     */
    public function render(): void
    {
        if (! $this->authorization->can(get_current_user_id(), Capabilities::BOOKKEEPING_MANAGE)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-bookkeeping'));
        }

        echo '<div class="wrap"><h1>' . esc_html__('Bookkeeping Subscriptions', 'bizupkeep-bookkeeping') . '</h1>';

        $rows = $this->buildRows();

        if ($rows === []) {
            echo '<p>' . esc_html__('No companies have a bookkeeping subscription yet.', 'bizupkeep-bookkeeping')
                . '</p></div>';

            return;
        }

        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Company', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Status', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Paid Until', 'bizupkeep-bookkeeping') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['companyName']) . '</td>';
            echo '<td>' . esc_html($row['statusLabel']) . '</td>';
            echo '<td>' . esc_html($row['paidUntil']) . '</td>';
            echo '<td><a href="' . esc_url($this->manageUrl($row['companyUuid'])) . '">'
                . esc_html__('Manage', 'bizupkeep-bookkeeping') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * @return array<int,array{companyUuid:string,companyName:string,statusLabel:string,paidUntil:string}>
     */
    private function buildRows(): array
    {
        $now = new DateTimeImmutable();
        $windowEnd = $now->modify(sprintf('+%d days', self::EXPIRING_SOON_WINDOW_DAYS));

        /** @var Subscription[] $subscriptions */
        $subscriptions = array_values(array_filter(
            $this->subscriptions->findAll(),
            static fn (Subscription $subscription): bool => $subscription->paidUntil !== null
        ));

        usort(
            $subscriptions,
            static fn (Subscription $a, Subscription $b): int => $a->paidUntil <=> $b->paidUntil
        );

        $rows = [];

        foreach ($subscriptions as $subscription) {
            $rows[] = [
                'companyUuid' => $subscription->companyUuid,
                'companyName' => $this->resolveCompanyName($subscription->companyUuid),
                'statusLabel' => $this->statusLabel($subscription, $now, $windowEnd),
                'paidUntil' => $subscription->paidUntil?->format('Y-m-d') ?? '',
            ];
        }

        return $rows;
    }

    private function resolveCompanyName(string $companyUuid): string
    {
        try {
            return $this->companies->getCompany($companyUuid)->getCompanyName();
        } catch (Throwable) {
            return $companyUuid;
        }
    }

    private function statusLabel(
        Subscription $subscription,
        DateTimeImmutable $now,
        DateTimeImmutable $windowEnd
    ): string {
        if ($subscription->isSuspended) {
            return __('Suspended', 'bizupkeep-bookkeeping');
        }

        if (! $subscription->isActive($now)) {
            return __('Lapsed', 'bizupkeep-bookkeeping');
        }

        if ($subscription->paidUntil !== null && $subscription->paidUntil <= $windowEnd) {
            return __('Expiring Soon', 'bizupkeep-bookkeeping');
        }

        return __('Active', 'bizupkeep-bookkeeping');
    }

    private function manageUrl(string $companyUuid): string
    {
        return add_query_arg(
            ['page' => ManualJournalEntryPage::SLUG, 'company' => $companyUuid],
            admin_url('admin.php')
        );
    }
}
