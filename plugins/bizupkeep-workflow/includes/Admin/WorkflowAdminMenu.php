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
 * Registers BizUpKeep Workflow's admin screen as a submenu of BizHub's
 * own top-level "BizHub" admin menu, rather than adding a second
 * top-level menu entry.
 *
 * @package BizHub\Workflow\Admin
 */
final class WorkflowAdminMenu
{
    /**
     * @var array<int,string>
     */
    private const LISTED_TYPES = [
        CompanyRegistrationDefinition::TYPE,
        CompanyAmendmentDefinition::TYPE,
        AnnualReturnDefinition::TYPE,
    ];

    /**
     * Register the 'admin_menu' hook.
     */
    public function register(): void
    {
        add_submenu_page(
            'bizhub',
            __('Workflows', 'bizupkeep-workflow'),
            __('Workflows', 'bizupkeep-workflow'),
            Capabilities::WORKFLOW_VIEW,
            'bizupkeep-workflow',
            [$this, 'render']
        );
    }

    /**
     * Render the Company Registration workflow list.
     */
    public function render(): void
    {
        // Was hardcoded to 'manage_options', inconsistent with every
        // other access point (the REST controller gates itself via
        // Capabilities::WORKFLOW_VIEW/WORKFLOW_TRANSITION) - this is a
        // real WP capability, added to bizhub_staff/bizhub_manager/
        // bizhub_administrator/administrator by Install/RoleGrant.php,
        // so current_user_can() works against it exactly like any
        // native capability.
        if (! current_user_can(Capabilities::WORKFLOW_VIEW)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bizupkeep-workflow'));
        }

        $application = function_exists('bizhub') ? bizhub() : null;
        $repository = $application?->container()->get(WorkflowRepositoryInterface::class);
        $companies = $application?->container()->get(CompanyServiceInterface::class);

        $statusFilter = $this->statusFilterFromRequest();

        $summaries = [];

        foreach (self::LISTED_TYPES as $type) {
            $typeSummaries = $statusFilter !== null
                ? ($repository?->summariesByStatus($type, $statusFilter, 500) ?? [])
                : ($repository?->summaries($type, 500) ?? []);

            foreach ($typeSummaries as $summary) {
                $summaries[] = $summary;
            }
        }

        usort(
            $summaries,
            static fn (WorkflowSummary $a, WorkflowSummary $b): int =>
                ($b->updatedAt ?? $b->createdAt) <=> ($a->updatedAt ?? $a->createdAt)
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Workflows', 'bizupkeep-workflow') . '</h1>';

        $this->renderStatusFilter($statusFilter);

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Type', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Company', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Status', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Created', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Updated', 'bizupkeep-workflow') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        if ($summaries === []) {
            echo '<tr><td colspan="6">'
                . esc_html__('No workflows yet.', 'bizupkeep-workflow')
                . '</td></tr>';
        }

        $now = new DateTimeImmutable();

        foreach ($summaries as $summary) {
            $viewUrl = add_query_arg(
                ['page' => QualityReviewPage::SLUG, 'workflow' => $summary->uuid],
                admin_url('admin.php')
            );

            $companyLabel = $this->companyLabel($companies, $summary->subjectUuid);

            $isOverdue = ! $summary->status->isTerminal()
                && ($summary->updatedAt ?? $summary->createdAt)->diff($now)->days >= OverdueThreshold::DAYS;

            echo '<tr' . ($isOverdue ? ' style="background-color:#fcf0f1;"' : '') . '>';
            echo '<td>' . esc_html(self::typeLabel($summary->workflowType)) . '</td>';
            echo '<td>' . esc_html($companyLabel) . '</td>';
            echo '<td>' . esc_html($summary->status->label());

            if ($isOverdue) {
                echo ' <span style="color:#b32d2e;font-weight:600;">'
                    . esc_html__('(overdue)', 'bizupkeep-workflow')
                    . '</span>';
            }

            echo '</td>';
            echo '<td>' . esc_html($summary->createdAt->format('Y-m-d H:i')) . '</td>';
            echo '<td>' . esc_html($summary->updatedAt?->format('Y-m-d H:i') ?? '') . '</td>';
            echo '<td><a class="button" href="' . esc_url($viewUrl) . '">'
                . esc_html__('View', 'bizupkeep-workflow') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * Render a simple GET-based status filter dropdown, preserving the
     * page slug so choosing an option reloads the list filtered to it.
     */
    private function renderStatusFilter(?WorkflowStatus $current): void
    {
        echo '<form method="get" style="margin-bottom:1em;">';
        echo '<input type="hidden" name="page" value="' . esc_attr('bizupkeep-workflow') . '" />';
        echo '<label for="bizupkeep-workflow-status-filter">'
            . esc_html__('Status:', 'bizupkeep-workflow') . '</label> ';
        echo '<select id="bizupkeep-workflow-status-filter" name="status" onchange="this.form.submit()">';
        echo '<option value="">' . esc_html__('All', 'bizupkeep-workflow') . '</option>';

        foreach (WorkflowStatus::cases() as $status) {
            echo '<option value="' . esc_attr($status->value) . '"'
                . selected($current?->value, $status->value, false) . '>'
                . esc_html($status->label()) . '</option>';
        }

        echo '</select> <noscript><button type="submit" class="button">'
            . esc_html__('Filter', 'bizupkeep-workflow') . '</button></noscript>';
        echo '</form>';
    }

    /**
     * Parse the 'status' query arg into a WorkflowStatus, or null for
     * "All" / an unrecognized value.
     */
    private function statusFilterFromRequest(): ?WorkflowStatus
    {
        $raw = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

        foreach (WorkflowStatus::cases() as $status) {
            if ($status->value === $raw) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Resolve a workflow's subject UUID to its company name, falling
     * back to the raw UUID if the company record is missing or the
     * Companies module is unavailable - staff reviewing this list need
     * to recognise applications by name, not by an opaque identifier.
     */
    private function companyLabel(?CompanyServiceInterface $companies, string $companyUuid): string
    {
        if ($companies === null) {
            return $companyUuid;
        }

        try {
            return $companies->getCompany($companyUuid)->getCompanyName();
        } catch (CompanyNotFoundException) {
            return $companyUuid;
        }
    }

    private static function typeLabel(string $workflowType): string
    {
        return match ($workflowType) {
            CompanyRegistrationDefinition::TYPE => __('Company Registration', 'bizupkeep-workflow'),
            CompanyAmendmentDefinition::TYPE => __('Company Amendment', 'bizupkeep-workflow'),
            AnnualReturnDefinition::TYPE => __('Annual Return', 'bizupkeep-workflow'),
            default => $workflowType,
        };
    }
}
