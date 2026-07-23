<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
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
 * A Kanban-style board view of every workflow across all three types:
 * one column per status, applications as cards within their current
 * column. Neither the flat "Workflows" list (WorkflowAdminMenu, one
 * status visible at a time via a dropdown filter) nor the
 * QualityReview-only queue give staff a single glance at where
 * everything currently sits across the whole pipeline - this does.
 *
 * Deliberately read-only: a card's "View" link opens the real
 * QualityReviewPage detail view, where any status change still goes
 * through that page's guarded actions (or the Override Status escape
 * hatch) - there is no drag-and-drop here forcing a workflow between
 * columns, which would need to reproduce every guard/transition rule
 * this page has no business re-implementing.
 *
 * @package BizHub\Workflow\Admin
 */
final class WorkflowBoardPage
{
    public const SLUG = 'bizupkeep-workflow-board';

    /**
     * Every workflow type this board covers, in display order.
     *
     * @var array<int,string>
     */
    private const LISTED_TYPES = [
        CompanyRegistrationDefinition::TYPE,
        CompanyAmendmentDefinition::TYPE,
        AnnualReturnDefinition::TYPE,
    ];

    /**
     * Status columns shown by default - the ones staff actually work
     * through day to day. Deliberately its own explicit order (not
     * WorkflowStatus::cases()' declaration order) so NamesRejected sits
     * right after QualityReview, the status it forks from and returns
     * to, rather than trailing at the end.
     *
     * @var array<int,WorkflowStatus>
     */
    private const ACTIVE_STATUSES = [
        WorkflowStatus::Created,
        WorkflowStatus::PendingDocuments,
        WorkflowStatus::DocumentsVerified,
        WorkflowStatus::AwaitingPayment,
        WorkflowStatus::Processing,
        WorkflowStatus::QualityReview,
        WorkflowStatus::NamesRejected,
    ];

    /**
     * Additional columns shown only with ?show_all=1 - conclusions
     * (successful or not), which staff don't need in view day to day
     * but occasionally want to check.
     *
     * @var array<int,WorkflowStatus>
     */
    private const TERMINAL_STATUSES = [
        WorkflowStatus::Completed,
        WorkflowStatus::Archived,
        WorkflowStatus::Cancelled,
        WorkflowStatus::Rejected,
    ];

    /**
     * How many days a non-terminal workflow may sit without an update
     * before its card is flagged overdue. Same threshold as
     * WorkflowDashboardPage::OVERDUE_DAYS/WorkflowAdminMenu::OVERDUE_DAYS -
     * kept in sync deliberately, not extracted to a shared constant
     * (no shared base class exists between these admin pages).
     */
    private const OVERDUE_DAYS = 7;

    /**
     * Upper bound on how many workflow instances of a single type are
     * scanned to build the board. Generous for the business volume
     * this runs at - see QualityReviewPage::SCAN_LIMIT for the same
     * reasoning.
     */
    private const SCAN_LIMIT = 500;

    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly CompanyServiceInterface $companies,
    ) {
    }

    /**
     * Render the board. Registered as the admin_menu callback for
     * self::SLUG.
     */
    public function render(): void
    {
        if (! current_user_can(Capabilities::WORKFLOW_VIEW)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bizupkeep-workflow'));
        }

        $showAll = isset($_GET['show_all']) && $_GET['show_all'] === '1';
        $summaries = $this->allSummaries();
        $now = new DateTimeImmutable();

        $columns = $showAll
            ? array_merge(self::ACTIVE_STATUSES, self::TERMINAL_STATUSES)
            : self::ACTIVE_STATUSES;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Workflows Board', 'bizupkeep-workflow') . '</h1>';

        $this->renderToggle($showAll);
        $this->renderStyles();
        $this->renderBoard($columns, $summaries, $now);

        echo '</div>';
    }

    /**
     * A link toggling ?show_all=1, revealing/hiding the four terminal
     * columns.
     */
    private function renderToggle(bool $showAll): void
    {
        $url = $showAll
            ? remove_query_arg('show_all')
            : add_query_arg('show_all', '1');

        echo '<p><a href="' . esc_url($url) . '" class="button">' . esc_html(
            $showAll
                ? __('Hide Completed/Archived/Cancelled/Rejected', 'bizupkeep-workflow')
                : __('Show Completed/Archived/Cancelled/Rejected', 'bizupkeep-workflow')
        ) . '</a></p>';
    }

    /**
     * Inline styles for the board layout - a plain CSS flexbox of
     * independently-scrollable columns, no build step or external
     * asset needed for a page this size.
     */
    private function renderStyles(): void
    {
        echo '<style>
            .bizupkeep-board {
                display: flex; gap: 12px; align-items: flex-start;
                overflow-x: auto; padding-bottom: 1em;
            }
            .bizupkeep-board-column {
                background: #f0f0f1; border-radius: 4px; width: 260px;
                flex: 0 0 260px; max-height: 75vh;
                display: flex; flex-direction: column;
            }
            .bizupkeep-board-column-header {
                padding: 10px 12px; font-weight: 600;
                border-bottom: 1px solid #dcdcde;
                display: flex; justify-content: space-between;
            }
            .bizupkeep-board-column-body { overflow-y: auto; padding: 8px; }
            .bizupkeep-board-card {
                background: #fff; border: 1px solid #dcdcde; border-radius: 3px;
                padding: 8px 10px; margin-bottom: 8px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .bizupkeep-board-card.is-overdue { border-left: 3px solid #b32d2e; }
            .bizupkeep-board-card-type {
                font-size: 11px; text-transform: uppercase;
                color: #646970; letter-spacing: .02em;
            }
            .bizupkeep-board-card-company { font-weight: 600; margin: 2px 0 4px; }
            .bizupkeep-board-card-meta { font-size: 12px; color: #646970; margin-bottom: 6px; }
            .bizupkeep-board-card-overdue { color: #b32d2e; font-weight: 600; }
            .bizupkeep-board-empty { color: #646970; font-size: 12px; padding: 4px 2px; }
        </style>';
    }

    /**
     * @param array<int,WorkflowStatus>  $columns
     * @param array<int,WorkflowSummary> $summaries
     */
    private function renderBoard(array $columns, array $summaries, DateTimeImmutable $now): void
    {
        /** @var array<string,array<int,WorkflowSummary>> $byStatus */
        $byStatus = [];

        foreach ($summaries as $summary) {
            $byStatus[$summary->status->value][] = $summary;
        }

        echo '<div class="bizupkeep-board">';

        foreach ($columns as $status) {
            $items = $byStatus[$status->value] ?? [];

            usort(
                $items,
                static fn (WorkflowSummary $a, WorkflowSummary $b): int =>
                    ($b->updatedAt ?? $b->createdAt) <=> ($a->updatedAt ?? $a->createdAt)
            );

            echo '<div class="bizupkeep-board-column">';
            echo '<div class="bizupkeep-board-column-header"><span>' . esc_html($status->label()) . '</span>'
                . '<span>' . esc_html((string) count($items)) . '</span></div>';
            echo '<div class="bizupkeep-board-column-body">';

            if ($items === []) {
                echo '<p class="bizupkeep-board-empty">' . esc_html__('None', 'bizupkeep-workflow') . '</p>';
            }

            foreach ($items as $summary) {
                $this->renderCard($summary, $now);
            }

            echo '</div></div>';
        }

        echo '</div>';
    }

    private function renderCard(WorkflowSummary $summary, DateTimeImmutable $now): void
    {
        $company = $this->companyFor($summary);
        $companyName = $company?->getCompanyName() ?? __('(company record missing)', 'bizupkeep-workflow');
        $regNumber = $company?->getRegistrationNumber() ?? '—';
        $since = $summary->updatedAt ?? $summary->createdAt;
        $isOverdue = ! $summary->status->isTerminal() && $since->diff($now)->days >= self::OVERDUE_DAYS;
        $viewUrl = add_query_arg(
            ['page' => QualityReviewPage::SLUG, 'workflow' => $summary->uuid],
            admin_url('admin.php')
        );

        echo '<div class="bizupkeep-board-card' . ($isOverdue ? ' is-overdue' : '') . '">';
        echo '<div class="bizupkeep-board-card-type">' . esc_html($this->typeLabel($summary->workflowType)) . '</div>';
        echo '<div class="bizupkeep-board-card-company">' . esc_html($companyName) . '</div>';
        echo '<div class="bizupkeep-board-card-meta">' . esc_html($regNumber) . '<br />'
            . esc_html($since->format('Y-m-d H:i'));

        if ($isOverdue) {
            echo ' &mdash; <span class="bizupkeep-board-card-overdue">'
                . esc_html__('overdue', 'bizupkeep-workflow') . '</span>';
        }

        echo '</div>';
        echo '<a href="' . esc_url($viewUrl) . '" class="button button-small">'
            . esc_html__('View', 'bizupkeep-workflow') . '</a>';
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

    private function companyFor(WorkflowSummary $summary): ?Company
    {
        try {
            return $this->companies->getCompany($summary->subjectUuid);
        } catch (CompanyNotFoundException) {
            return null;
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
