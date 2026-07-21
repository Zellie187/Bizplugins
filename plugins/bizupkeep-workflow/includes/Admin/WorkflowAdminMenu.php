<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin;

use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\DTO\WorkflowSummary;
use BizHub\Workflow\Policies\Capabilities;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;

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

        $summaries = [];

        foreach (self::LISTED_TYPES as $type) {
            foreach ($repository?->summaries($type) ?? [] as $summary) {
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
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Type', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('UUID', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Company', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Status', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Created', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Updated', 'bizupkeep-workflow') . '</th>'
            . '</tr></thead><tbody>';

        if ($summaries === []) {
            echo '<tr><td colspan="6">'
                . esc_html__('No workflows yet.', 'bizupkeep-workflow')
                . '</td></tr>';
        }

        foreach ($summaries as $summary) {
            echo '<tr>';
            echo '<td>' . esc_html(self::typeLabel($summary->workflowType)) . '</td>';
            echo '<td>' . esc_html($summary->uuid) . '</td>';
            echo '<td>' . esc_html($summary->subjectUuid) . '</td>';
            echo '<td>' . esc_html($summary->status->label()) . '</td>';
            echo '<td>' . esc_html($summary->createdAt->format('Y-m-d H:i')) . '</td>';
            echo '<td>' . esc_html($summary->updatedAt?->format('Y-m-d H:i') ?? '') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
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
