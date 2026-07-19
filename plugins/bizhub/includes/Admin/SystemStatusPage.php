<?php

declare(strict_types=1);

namespace BizHub\Admin;

use BizHub\Framework\Support\Version;

/**
 * Renders the BizHub system status admin page.
 *
 * @package BizHub\Admin
 */
final class SystemStatusPage
{
    /**
     * Render the system status page.
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $yes = __('Yes', 'bizhub');
        $no = __('No', 'bizhub');

        $rows = [
            __('BizHub Version', 'bizhub') => Version::current(),
            __('PHP Version', 'bizhub') => PHP_VERSION,
            __('WordPress Version', 'bizhub') => get_bloginfo('version'),
            __('WooCommerce Active', 'bizhub') => class_exists('WooCommerce') ? $yes : $no,
            __('Forminator Active', 'bizhub') => class_exists('Forminator_API') ? $yes : $no,
        ];

        echo '<div class="wrap"><h1>' . esc_html__('BizHub System Status', 'bizhub') . '</h1>';
        echo '<table class="widefat striped"><tbody>';

        foreach ($rows as $label => $value) {
            echo '<tr><td><strong>' . esc_html($label) . '</strong></td><td>' . esc_html($value) . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }
}
