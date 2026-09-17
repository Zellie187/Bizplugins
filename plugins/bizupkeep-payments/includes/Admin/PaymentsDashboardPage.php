<?php

declare(strict_types=1);

namespace BizHub\Payments\Admin;

use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Contracts\PaymentConfirmationServiceInterface;
use BizHub\Payments\Entities\PaymentAttempt;
use BizHub\Payments\Enums\PaymentAttemptStatus;
use BizHub\Payments\Policies\Capabilities;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;

/**
 * Staff-facing view of every payment attempt, primarily useful for
 * spotting and manually retrying a "Succeeded but not fulfilled" row -
 * a payment the gateway confirmed (money captured) where the
 * downstream Invoice/workflow-confirmation step then threw (see
 * PaymentConfirmationServiceInterface's docblock). This is new,
 * genuinely more visible risk surface than the old WooCommerce design
 * had (its equivalent "A2Z revenue posting" failure was silent,
 * best-effort, and never surfaced anywhere) - this page is what makes
 * that visibility actually useful rather than just theoretical.
 *
 * @package BizHub\Payments\Admin
 */
final class PaymentsDashboardPage
{
    public const SLUG = 'bizupkeep-payments-dashboard';

    private const RETRY_NONCE_ACTION = 'bizupkeep_payments_retry';

    private const RETRY_NONCE_FIELD = 'bizupkeep_payments_retry_nonce';

    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $attempts,
        private readonly PaymentConfirmationServiceInterface $confirmation,
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    public function render(): void
    {
        if (! $this->authorization->can(get_current_user_id(), Capabilities::MANAGE_PAYMENTS)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-payments'));
        }

        $notice = $this->handleRetry();

        echo '<div class="wrap"><h1>' . esc_html__('Payments', 'bizupkeep-payments') . '</h1>';

        $this->renderNotice($notice);
        $this->renderTable();

        echo '</div>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleRetry(): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_payments_action') !== 'retry') {
            return null;
        }

        check_admin_referer(self::RETRY_NONCE_ACTION, self::RETRY_NONCE_FIELD);

        $uuid = $this->postParam('attempt_uuid');
        $attempt = $this->attempts->findByUuid($uuid);

        if ($attempt === null || $attempt->status !== PaymentAttemptStatus::Succeeded) {
            return ['error', __('That payment attempt cannot be retried.', 'bizupkeep-payments')];
        }

        $this->confirmation->confirm($attempt);

        $refreshed = $this->attempts->findByUuid($uuid);

        if ($refreshed !== null && $refreshed->fulfilledAt !== null) {
            return ['success', __('Retried successfully - payment is now fulfilled.', 'bizupkeep-payments')];
        }

        return ['error', __('Retry failed - payment is still not fulfilled, check the logs.', 'bizupkeep-payments')];
    }

    private function renderTable(): void
    {
        $attempts = $this->attempts->findAll();

        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Created', 'bizupkeep-payments') . '</th>'
            . '<th>' . esc_html__('Gateway', 'bizupkeep-payments') . '</th>'
            . '<th>' . esc_html__('Service', 'bizupkeep-payments') . '</th>'
            . '<th>' . esc_html__('Amount', 'bizupkeep-payments') . '</th>'
            . '<th>' . esc_html__('Status', 'bizupkeep-payments') . '</th>'
            . '<th>' . esc_html__('Invoice', 'bizupkeep-payments') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        foreach ($attempts as $attempt) {
            $this->renderRow($attempt);
        }

        echo '</tbody></table>';
    }

    private function renderRow(PaymentAttempt $attempt): void
    {
        $needsRetry = $attempt->status === PaymentAttemptStatus::Succeeded && $attempt->fulfilledAt === null;

        echo '<tr' . ($needsRetry ? ' style="background:#fcf0f1;"' : '') . '>';
        echo '<td>' . esc_html($attempt->createdAt->format('Y-m-d H:i')) . '</td>';
        echo '<td>' . esc_html($attempt->gateway->value) . '</td>';
        echo '<td><code>' . esc_html($attempt->serviceKey) . '</code></td>';
        echo '<td>R' . esc_html(number_format($attempt->amountMinor / 100, 2)) . '</td>';
        echo '<td>' . esc_html($attempt->status->value) . ($needsRetry
            ? ' <strong>' . esc_html__('(needs retry)', 'bizupkeep-payments') . '</strong>'
            : '') . '</td>';
        echo '<td>' . esc_html($attempt->invoiceUuid ?? '-') . '</td>';

        echo '<td>';

        if ($needsRetry) {
            echo '<form method="post">';
            wp_nonce_field(self::RETRY_NONCE_ACTION, self::RETRY_NONCE_FIELD);
            echo '<input type="hidden" name="bizupkeep_payments_action" value="retry" />';
            echo '<input type="hidden" name="attempt_uuid" value="' . esc_attr($attempt->uuid) . '" />';
            echo '<button type="submit" class="button">' . esc_html__('Retry', 'bizupkeep-payments') . '</button>';
            echo '</form>';
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
