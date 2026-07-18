<?php

declare(strict_types=1);

namespace BizHub\Admin;

/**
 * Renders the BizHub settings admin page.
 *
 * @package BizHub\Admin
 */
final class SettingsPage
{
    private const OPTION_KEY = 'bizhub_settings';
    private const DELETE_DATA_OPTION_KEY = 'bizhub_delete_data_on_uninstall';

    /**
     * Render the settings page.
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $nonce = isset($_POST['bizhub_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['bizhub_settings_nonce'])) : '';

        if ($nonce !== '' && wp_verify_nonce($nonce, 'bizhub_save_settings')) {
            $this->saveSettings();
        }

        $settings = get_option(self::OPTION_KEY, []);
        $deleteDataOnUninstall = (bool) get_option(self::DELETE_DATA_OPTION_KEY, false);

        echo '<div class="wrap"><h1>' . esc_html__('BizHub Settings', 'bizhub') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('bizhub_save_settings', 'bizhub_settings_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Support Email', 'bizhub') . '</th><td>';
        echo '<input type="email" name="support_email" class="regular-text" value="' . esc_attr($settings['support_email'] ?? '') . '">';
        echo '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Uninstall', 'bizhub') . '</th><td>';
        echo '<label><input type="checkbox" name="delete_data_on_uninstall" value="1"' . checked($deleteDataOnUninstall, true, false) . '> ';
        echo esc_html__('Delete all BizHub data (companies, applications, documents, etc.) when the plugin is deleted', 'bizhub');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Leave unchecked to keep your data if you reinstall the plugin later.', 'bizhub') . '</p>';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form></div>';
    }

    /**
     * Persist submitted settings.
     */
    private function saveSettings(): void
    {
        // Caller (render()) verifies the nonce before calling this method.
        $supportEmail = sanitize_email(wp_unslash($_POST['support_email'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $deleteDataOnUninstall = isset($_POST['delete_data_on_uninstall']); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        update_option(self::OPTION_KEY, [
            'support_email' => $supportEmail,
        ]);

        update_option(self::DELETE_DATA_OPTION_KEY, $deleteDataOnUninstall);
    }
}
