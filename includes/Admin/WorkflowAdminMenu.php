<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin;

use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
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
     * Register the 'admin_menu' hook.
     */
    public function register(): void
    {
        add_submenu_page(
            'bizhub',
            __('Workflows', 'bizupkeep-workflow'),
            __('Workflows', 'bizupkeep-workflow'),
            'manage_options',
            'bizupkeep-workflow',
            [$this, 'render']
        );
    }

    /**
     * Render the Company Registration workflow list.
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bizupkeep-workflow'));
        }

        $application = function_exists('bizhub') ? bizhub() : null;
        $repository = $application?->container()->get(WorkflowRepositoryInterface::class);

        $summaries = $repository?->summaries(CompanyRegistrationDefinition::TYPE) ?? [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Company Registration Workflows', 'bizupkeep-workflow') . '</h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('UUID', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Company', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Status', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Created', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Updated', 'bizupkeep-workflow') . '</th>'
            . '</tr></thead><tbody>';

        if ($summaries === []) {
            echo '<tr><td colspan="5">'
                . esc_html__('No Company Registration workflows yet.', 'bizupkeep-workflow')
                . '</td></tr>';
        }

        foreach ($summaries as $summary) {
            echo '<tr>';
            echo '<td>' . esc_html($summary->uuid) . '</td>';
            echo '<td>' . esc_html($summary->subjectUuid) . '</td>';
            echo '<td>' . esc_html($summary->status->label()) . '</td>';
            echo '<td>' . esc_html($summary->createdAt->format('Y-m-d H:i')) . '</td>';
            echo '<td>' . esc_html($summary->updatedAt?->format('Y-m-d H:i') ?? '') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
