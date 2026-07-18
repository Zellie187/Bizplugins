<?php

declare(strict_types=1);

namespace BizHub\Integrations\WooCommerce;

use WP_Error;

/**
 * Adds and validates BizHub-specific fields on the WooCommerce checkout.
 *
 * @package BizHub\Integrations\WooCommerce
 */
final class CheckoutHandler
{
    private const COMPANY_NAME_FIELD = 'bizhub_company_name';

    /**
     * Register WooCommerce checkout hooks.
     */
    public function register(): void
    {
        add_filter('woocommerce_checkout_fields', [$this, 'addFields']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateFields'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveFields']);
    }

    /**
     * Add BizHub-specific fields to the checkout form.
     *
     * @param array<string,array<string,mixed>> $fields
     *
     * @return array<string,array<string,mixed>>
     */
    public function addFields(array $fields): array
    {
        $fields['order'][self::COMPANY_NAME_FIELD] = [
            'type' => 'text',
            'label' => __('Company name (if applicable)', 'bizhub'),
            'required' => false,
            'class' => ['form-row-wide'],
        ];

        return $fields;
    }

    /**
     * Validate BizHub-specific checkout fields.
     *
     * @param array<string,mixed> $data
     */
    public function validateFields(array $data, WP_Error $errors): void
    {
        // No required fields yet; reserved for future validation rules.
    }

    /**
     * Persist BizHub-specific checkout fields onto the order.
     *
     * Fires on 'woocommerce_checkout_update_order_meta', which WooCommerce
     * only triggers after its own checkout nonce has already been verified.
     */
    public function saveFields(int $orderId): void
    {
        if (! isset($_POST[self::COMPANY_NAME_FIELD])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $companyName = sanitize_text_field(wp_unslash($_POST[self::COMPANY_NAME_FIELD])); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($companyName === '') {
            return;
        }

        update_post_meta($orderId, '_bizhub_company_name', $companyName);
    }
}
