<?php

declare(strict_types=1);

namespace BizHub\Admin;

use BizHub\Framework\Queue\Worker;
use BizHub\Notifications\NotificationQueue;

/**
 * Renders the BizHub tools admin page.
 *
 * @package BizHub\Admin
 */
final class ToolsPage
{
    public function __construct(
        private readonly Worker $queueWorker,
        private readonly NotificationQueue $notificationQueue
    ) {
    }

    /**
     * Render the tools page.
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $message = null;

        $nonce = isset($_POST['bizhub_tools_nonce']) ? sanitize_text_field(wp_unslash($_POST['bizhub_tools_nonce'])) : '';

        if ($nonce !== '' && wp_verify_nonce($nonce, 'bizhub_run_tool')) {
            $tool = isset($_POST['tool']) ? sanitize_text_field(wp_unslash($_POST['tool'])) : '';
            $message = $this->runTool($tool);
        }

        echo '<div class="wrap"><h1>' . esc_html__('BizHub Tools', 'bizhub') . '</h1>';

        if ($message !== null) {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field('bizhub_run_tool', 'bizhub_tools_nonce');
        echo '<p><button type="submit" name="tool" value="process_queue" class="button button-primary">' . esc_html__('Process Job Queue', 'bizhub') . '</button></p>';
        echo '<p><button type="submit" name="tool" value="process_notifications" class="button button-primary">' . esc_html__('Process Notification Queue', 'bizhub') . '</button></p>';
        echo '</form></div>';
    }

    /**
     * Run the requested tool action.
     */
    private function runTool(string $tool): string
    {
        return match ($tool) {
            'process_queue' => sprintf(
                /* translators: %d: number of jobs processed */
                __('Processed %d queued job(s).', 'bizhub'),
                $this->queueWorker->work()
            ),
            'process_notifications' => sprintf(
                /* translators: %d: number of notifications delivered */
                __('Delivered %d queued notification(s).', 'bizhub'),
                $this->notificationQueue->processPending()
            ),
            default => __('Unknown tool.', 'bizhub'),
        };
    }
}
