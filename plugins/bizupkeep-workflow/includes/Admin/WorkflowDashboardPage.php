<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\DTO\WorkflowSummary;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Policies\Capabilities;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use DateTimeImmutable;

/**
 * A dashboard-style overview of every workflow across all three types:
 * counts by status and type, and an "overdue" callout for whatever is
 * stalled - the count/aging view that neither the flat "Workflows"
 * list (WorkflowAdminMenu) nor the QualityReview-only queue
 * (QualityReviewPage::renderQueue()) provides on its own.
 *
 * @package BizHub\Workflow\Admin
 */
final class WorkflowDashboardPage
{
    public const SLUG = 'bizupkeep-workflow-dashboard';

    /**
     * @var array<int,string>
     */
    private const LISTED_TYPES = [
        CompanyRegistrationDefinition::TYPE,
        CompanyAmendmentDefinition::TYPE,
        AnnualReturnDefinition::TYPE,
    ];

    /**
     * How many days a non-terminal workflow may sit without an update
     * before it counts as overdue. See WorkflowAdminMenu::OVERDUE_DAYS -
     * kept as the same value deliberately, so a row flagged overdue
     * there is the same row counted overdue here.
     */
    private const OVERDUE_DAYS = 7;

    /**
     * Upper bound on how many workflow instances of a single type are
     * scanned to build the dashboard. Generous for the business volume
     * this runs at - see QualityReviewPage::SCAN_LIMIT for the same
     * reasoning.
     */
    private const SCAN_LIMIT = 500;

    /**
     * How many of the most-overdue applications to list individually.
     */
    private const OVERDUE_LIST_LIMIT = 15;

    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly CompanyServiceInterface $companies,
    ) {
    }

    /**
     * Render the dashboard. Registered as the admin_menu callback for
     * self::SLUG.
     */
    public function render(): void
    {
        if (! current_user_can(Capabilities::WORKFLOW_VIEW)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bizupkeep-workflow'));
        }

        $summaries = $this->allSummaries();
        $now = new DateTimeImmutable();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Workflow Dashboard', 'bizupkeep-workflow') . '</h1>';

        $this->renderTotals($summaries, $now);
        $this->renderStatusBreakdown($summaries);
        $this->renderOverdueList($summaries, $now);

        echo '</div>';
    }

    /**
     * @return array<int,WorkflowSummary>
     */
    private function allSummaries(): array
    {
        $summaries = [];

        foreach (self::LISTED_TYPES as $type) {
            foreach ($this->workflows->summaries($type, self::SCAN_LIMIT) as $summary) {
                $summaries[] = $summary;
            }
        }

        return $summaries;
    }

    /**
     * @param array<int,WorkflowSummary> $summaries
     */
    private function renderTotals(array $summaries, DateTimeImmutable $now): void
    {
        $total = count($summaries);
        $open = 0;
        $overdue = 0;

        foreach ($summaries as $summary) {
            if ($summary->status->isTerminal()) {
                continue;
            }

            $open++;

            if ($this->isOverdue($summary, $now)) {
                $overdue++;
            }
        }

        echo '<div class="bizupkeep-dashboard-totals" style="display:flex;gap:2em;margin:1em 0;">';
        $this->renderStatTile(__('Total Applications', 'bizupkeep-workflow'), (string) $total);
        $this->renderStatTile(__('Open (Non-Terminal)', 'bizupkeep-workflow'), (string) $open);
        $this->renderStatTile(__('Overdue', 'bizupkeep-workflow'), (string) $overdue, $overdue > 0);
        echo '</div>';
    }

    private function renderStatTile(string $label, string $value, bool $alert = false): void
    {
        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:1em 1.5em;min-width:10em;">';
        echo '<div style="font-size:2em;font-weight:600;' . ($alert ? 'color:#b32d2e;' : '') . '">'
            . esc_html($value) . '</div>';
        echo '<div>' . esc_html($label) . '</div>';
        echo '</div>';
    }

    /**
     * A status x type count matrix, each cell linking into the
     * (status-filtered) Workflows list.
     *
     * @param array<int,WorkflowSummary> $summaries
     */
    private function renderStatusBreakdown(array $summaries): void
    {
        /** @var array<string,array<string,int>> $counts [status][type] => count */
        $counts = [];

        foreach ($summaries as $summary) {
            $statusValue = $summary->status->value;
            $counts[$statusValue][$summary->workflowType] = ($counts[$statusValue][$summary->workflowType] ?? 0) + 1;
        }

        echo '<h2>' . esc_html__('By Status', 'bizupkeep-workflow') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Status', 'bizupkeep-workflow') . '</th>';

        foreach (self::LISTED_TYPES as $type) {
            echo '<th>' . esc_html($this->typeLabel($type)) . '</th>';
        }

        echo '<th>' . esc_html__('Total', 'bizupkeep-workflow') . '</th></tr></thead><tbody>';

        foreach (WorkflowStatus::cases() as $status) {
            $rowTotal = array_sum($counts[$status->value] ?? []);

            if ($rowTotal === 0) {
                continue;
            }

            $filterUrl = add_query_arg(
                ['page' => 'bizupkeep-workflow', 'status' => $status->value],
                admin_url('admin.php')
            );

            echo '<tr><td><a href="' . esc_url($filterUrl) . '">' . esc_html($status->label()) . '</a></td>';

            foreach (self::LISTED_TYPES as $type) {
                echo '<td>' . esc_html((string) ($counts[$status->value][$type] ?? 0)) . '</td>';
            }

            echo '<td><strong>' . esc_html((string) $rowTotal) . '</strong></td></tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * List the most-overdue non-terminal applications individually, so
     * staff can jump straight to whichever ones have stalled longest
     * rather than hunting through the full list.
     *
     * @param array<int,WorkflowSummary> $summaries
     */
    private function renderOverdueList(array $summaries, DateTimeImmutable $now): void
    {
        $overdue = array_values(array_filter(
            $summaries,
            fn (WorkflowSummary $summary): bool => $this->isOverdue($summary, $now)
        ));

        usort(
            $overdue,
            static fn (WorkflowSummary $a, WorkflowSummary $b): int =>
                ($a->updatedAt ?? $a->createdAt) <=> ($b->updatedAt ?? $b->createdAt)
        );

        echo '<h2>' . esc_html(sprintf(
            /* translators: %d: number of days */
            __('Overdue (no update in %d+ days)', 'bizupkeep-workflow'),
            self::OVERDUE_DAYS
        )) . '</h2>';

        if ($overdue === []) {
            echo '<p>' . esc_html__('Nothing is currently overdue.', 'bizupkeep-workflow') . '</p>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Type', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Company', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Status', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Days Since Last Update', 'bizupkeep-workflow') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        foreach (array_slice($overdue, 0, self::OVERDUE_LIST_LIMIT) as $summary) {
            $since = $summary->updatedAt ?? $summary->createdAt;
            $days = $since->diff($now)->days;
            $viewUrl = add_query_arg(
                ['page' => QualityReviewPage::SLUG, 'workflow' => $summary->uuid],
                admin_url('admin.php')
            );

            echo '<tr>'
                . '<td>' . esc_html($this->typeLabel($summary->workflowType)) . '</td>'
                . '<td>' . esc_html($this->companyLabel($summary->subjectUuid)) . '</td>'
                . '<td>' . esc_html($summary->status->label()) . '</td>'
                . '<td>' . esc_html((string) $days) . '</td>'
                . '<td><a class="button" href="' . esc_url($viewUrl) . '">'
                . esc_html__('Review', 'bizupkeep-workflow') . '</a></td>'
                . '</tr>';
        }

        echo '</tbody></table>';

        if (count($overdue) > self::OVERDUE_LIST_LIMIT) {
            echo '<p>' . esc_html(sprintf(
                /* translators: %d: number of additional overdue applications not shown */
                __('...and %d more.', 'bizupkeep-workflow'),
                count($overdue) - self::OVERDUE_LIST_LIMIT
            )) . '</p>';
        }
    }

    private function isOverdue(WorkflowSummary $summary, DateTimeImmutable $now): bool
    {
        if ($summary->status->isTerminal()) {
            return false;
        }

        return ($summary->updatedAt ?? $summary->createdAt)->diff($now)->days >= self::OVERDUE_DAYS;
    }

    private function companyLabel(string $companyUuid): string
    {
        try {
            return $this->companies->getCompany($companyUuid)->getCompanyName();
        } catch (CompanyNotFoundException) {
            return $companyUuid;
        }
    }

    private function typeLabel(string $workflowType): string
    {
        return match ($workflowType) {
            CompanyRegistrationDefinition::TYPE => __('Company Registration', 'bizupkeep-workflow'),
            CompanyAmendmentDefinition::TYPE => __('Company Amendment', 'bizupkeep-workflow'),
            AnnualReturnDefinition::TYPE => __('Annual Return', 'bizupkeep-workflow'),
            default => $workflowType,
        };
    }
}
