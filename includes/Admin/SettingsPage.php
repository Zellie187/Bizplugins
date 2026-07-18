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

        echo '<div class="wrap"><h1>' . esc_html__('BizHub Settings', 'bizhub') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('bizhub_save_settings', 'bizhub_settings_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Support Email', 'bizhub') . '</th><td>';
        echo '<input type="email" name="support_email" class="regular-text" value="' . esc_attr($settings['support_email'] ?? '') . '">';
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

        update_option(self::OPTION_KEY, [
            'support_email' => $supportEmail,
        ]);
    }
}
