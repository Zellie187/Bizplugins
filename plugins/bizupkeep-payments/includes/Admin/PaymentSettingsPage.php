<?php

declare(strict_types=1);

namespace BizHub\Payments\Admin;

use BizHub\Payments\Policies\Capabilities;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;

/**
 * Staff-facing gateway credential settings, stored as WP options -
 * matching this codebase's existing config-storage convention (see
 * bizhub's own SettingsPage), since no secrets-manager exists anywhere
 * in this ecosystem yet.
 *
 * @package BizHub\Payments\Admin
 */
final class PaymentSettingsPage
{
    public const SLUG = 'bizupkeep-payments-settings';

    private const SAVE_NONCE_ACTION = 'bizupkeep_payments_settings_save';

    private const SAVE_NONCE_FIELD = 'bizupkeep_payments_settings_save_nonce';

    /** @var array<int,array{option:string,label:string,type:string}> */
    private const FIELDS = [
        ['option' => 'bizupkeep_payments_yoco_public_key', 'label' => 'Yoco Public Key', 'type' => 'text'],
        ['option' => 'bizupkeep_payments_yoco_secret_key', 'label' => 'Yoco Secret Key', 'type' => 'password'],
        ['option' => 'bizupkeep_payments_yoco_webhook_secret', 'label' => 'Yoco Webhook Secret', 'type' => 'password'],
        ['option' => 'bizupkeep_payments_snapscan_snap_code', 'label' => 'SnapScan Snap Code', 'type' => 'text'],
        [
            'option' => 'bizupkeep_payments_snapscan_webhook_key',
            'label' => 'SnapScan Webhook Key',
            'type' => 'password',
        ],
    ];

    public function __construct(
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    public function render(): void
    {
        if (! $this->authorization->can(get_current_user_id(), Capabilities::MANAGE_PAYMENTS)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-payments'));
        }

        $notice = $this->handleSave();

        echo '<div class="wrap"><h1>' . esc_html__('Payment Gateway Settings', 'bizupkeep-payments') . '</h1>';

        $this->renderNotice($notice);
        $this->renderForm();

        echo '</div>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleSave(): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_payments_settings_action') !== 'save') {
            return null;
        }

        check_admin_referer(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        foreach (self::FIELDS as $field) {
            $value = $this->postParam($field['option']);

            if ($value === '') {
                continue;
            }

            update_option($field['option'], $value, false);
        }

        return ['success', __('Settings saved.', 'bizupkeep-payments')];
    }

    private function renderForm(): void
    {
        echo '<form method="post"><table class="form-table"><tbody>';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_payments_settings_action" value="save" />';

        foreach (self::FIELDS as $field) {
            $this->renderField($field['option'], $field['label'], $field['type']);
        }

        echo '</tbody></table>';
        submit_button(__('Save Settings', 'bizupkeep-payments'));
        echo '</form>';
    }

    private function renderField(string $option, string $label, string $type): void
    {
        $value = get_option($option, '');
        $value = is_string($value) ? $value : '';
        $isSecret = $type === 'password';

        // A secret's current value is never echoed back into the page
        // HTML, even escaped - only whether one is already set.
        $fieldValue = $isSecret ? '' : $value;

        echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($option) . '" name="' . esc_attr($option)
            . '" value="' . esc_attr($fieldValue) . '" class="regular-text" autocomplete="off" />';

        if ($isSecret) {
            echo '<p class="description">' . ($value !== ''
                ? esc_html__('A value is already set. Leave blank to keep it unchanged.', 'bizupkeep-payments')
                : esc_html__('Not set yet.', 'bizupkeep-payments')) . '</p>';
        }

        echo '</td></tr>';
    }

    /**
     * @param array{0:string,1:string}|null $notice
     */
    private function renderNotice(?array $notice): void
    {
        if ($notice === null) {
            return;
        }

        [$type, $message] = $notice;
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>'
            . esc_html($message) . '</p></div>';
    }

    private function postParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the caller before this is used.
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function requestMethod(): string
    {
        return isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    }
}
