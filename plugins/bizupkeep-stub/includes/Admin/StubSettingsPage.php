<?php

declare(strict_types=1);

namespace BizHub\Stub\Admin;

use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Stub\Contracts\StubApiClientInterface;
use BizHub\Stub\Policies\Capabilities;

/**
 * Staff-facing Stub API credential settings, stored as WP options -
 * same convention every other gateway/integration in this ecosystem
 * uses (see bizupkeep-payments' PaymentSettingsPage).
 *
 * @package BizHub\Stub\Admin
 */
final class StubSettingsPage
{
    public const SLUG = 'bizupkeep-stub-settings';

    private const SAVE_NONCE_ACTION = 'bizupkeep_stub_settings_save';

    private const SAVE_NONCE_FIELD = 'bizupkeep_stub_settings_save_nonce';

    private const VERIFY_NONCE_ACTION = 'bizupkeep_stub_settings_verify';

    private const VERIFY_NONCE_FIELD = 'bizupkeep_stub_settings_verify_nonce';

    /** @var array<int,array{option:string,label:string,type:string}> */
    private const FIELDS = [
        ['option' => 'bizupkeep_stub_api_key', 'label' => 'API Key', 'type' => 'password'],
        ['option' => 'bizupkeep_stub_app_id', 'label' => 'App ID', 'type' => 'text'],
        ['option' => 'bizupkeep_stub_environment', 'label' => 'Environment (test or live)', 'type' => 'text'],
    ];

    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly StubApiClientInterface $api
    ) {
    }

    public function render(): void
    {
        if (! $this->authorization->can(get_current_user_id(), Capabilities::MANAGE_STUB)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-stub'));
        }

        $notice = $this->handleSave() ?? $this->handleVerify();

        echo '<div class="wrap"><h1>' . esc_html__('Stub Settings', 'bizupkeep-stub') . '</h1>';

        $this->renderNotice($notice);
        $this->renderForm();
        $this->renderVerifyForm();

        echo '</div>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleSave(): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_stub_settings_action') !== 'save') {
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

        return ['success', __('Settings saved.', 'bizupkeep-stub')];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleVerify(): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_stub_settings_action') !== 'verify') {
            return null;
        }

        check_admin_referer(self::VERIFY_NONCE_ACTION, self::VERIFY_NONCE_FIELD);

        return $this->api->verifyApiKey()
            ? ['success', __('API key verified - Stub accepted it.', 'bizupkeep-stub')]
            : ['error', __('Stub rejected this API key/App ID combination (or the environment is wrong).', 'bizupkeep-stub')];
    }

    private function renderForm(): void
    {
        echo '<form method="post"><table class="form-table"><tbody>';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_stub_settings_action" value="save" />';

        foreach (self::FIELDS as $field) {
            $this->renderField($field['option'], $field['label'], $field['type']);
        }

        echo '</tbody></table>';
        submit_button(__('Save Settings', 'bizupkeep-stub'));
        echo '</form>';
    }

    private function renderVerifyForm(): void
    {
        echo '<form method="post">';
        wp_nonce_field(self::VERIFY_NONCE_ACTION, self::VERIFY_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_stub_settings_action" value="verify" />';
        submit_button(__('Verify API Key', 'bizupkeep-stub'), 'secondary');
        echo '</form>';
    }

    private function renderField(string $option, string $label, string $type): void
    {
        $value = get_option($option, '');
        $value = is_string($value) ? $value : '';
        $isSecret = $type === 'password';

        $fieldValue = $isSecret ? '' : $value;

        echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($option) . '" name="' . esc_attr($option)
            . '" value="' . esc_attr($fieldValue) . '" class="regular-text" autocomplete="off" />';

        if ($isSecret) {
            echo '<p class="description">' . ($value !== ''
                ? esc_html__('A value is already set. Leave blank to keep it unchanged.', 'bizupkeep-stub')
                : esc_html__('Not set yet.', 'bizupkeep-stub')) . '</p>';
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
