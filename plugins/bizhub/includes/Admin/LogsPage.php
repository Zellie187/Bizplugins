<?php

declare(strict_types=1);

namespace BizHub\Admin;

/**
 * Renders the BizHub logs admin page.
 *
 * @package BizHub\Admin
 */
final class LogsPage
{
    private const MAX_LINES = 200;

    /**
     * Render the logs page.
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('BizHub Logs', 'bizhub') . '</h1>';
        echo '<pre style="background:#fff;padding:1em;overflow:auto;max-height:600px;">';
        echo esc_html($this->readRecentLines());
        echo '</pre></div>';
    }

    /**
     * Read the most recent lines from the log file.
     */
    private function readRecentLines(): string
    {
        $path = \defined('BIZHUB_STORAGE_PATH') ? BIZHUB_STORAGE_PATH . 'logs/bizhub.log' : '';

        if ($path === '' || ! is_file($path)) {
            return __('No log entries yet.', 'bizhub');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $recent = array_slice($lines, -self::MAX_LINES);

        return implode("\n", $recent);
    }
}
