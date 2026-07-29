<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Admin;

use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\Contracts\LedgerServiceInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\DTO\JournalLineData;
use BizHub\Bookkeeping\DTO\ManualJournalEntryData;
use BizHub\Bookkeeping\Entities\Account;
use BizHub\Bookkeeping\Entities\CompanySettings;
use BizHub\Bookkeeping\Enums\JournalSource;
use BizHub\Bookkeeping\Exceptions\BookkeepingException;
use BizHub\Bookkeeping\Policies\Capabilities;
use BizHub\Bookkeeping\Support\Money;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Framework\Support\Uuid;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use DateTimeImmutable;

/**
 * Staff-facing "Bookkeeping" admin screen: post arbitrary balanced
 * multi-line manual journal entries (opening balances, adjustments,
 * depreciation - anything the simplified client-facing capture form
 * can't express, including VAT postings for this round) and reverse
 * existing entries. Gated by BOOKKEEPING_MANAGE, deliberately stricter
 * than the client-facing capabilities.
 *
 * Thin by the same design as bizupkeep-workflow's QualityReviewPage:
 * every mutation is delegated to LedgerService, this page only adds
 * the company-scoping, form parsing and read-side listing needed to
 * actually use it.
 *
 * @package BizHub\Bookkeeping\Admin
 */
final class ManualJournalEntryPage
{
    public const SLUG = 'bizupkeep-bookkeeping';

    private const NONCE_ACTION = 'bizupkeep_bookkeeping_manual_entry';

    private const NONCE_FIELD = 'bizupkeep_bookkeeping_manual_entry_nonce';

    private const REVERSE_NONCE_ACTION = 'bizupkeep_bookkeeping_reverse_entry';

    private const REVERSE_NONCE_FIELD = 'bizupkeep_bookkeeping_reverse_entry_nonce';

    private const EXTEND_SUBSCRIPTION_NONCE_ACTION = 'bizupkeep_bookkeeping_extend_subscription';

    private const EXTEND_SUBSCRIPTION_NONCE_FIELD = 'bizupkeep_bookkeeping_extend_subscription_nonce';

    private const SUSPEND_SUBSCRIPTION_NONCE_ACTION = 'bizupkeep_bookkeeping_suspend_subscription';

    private const SUSPEND_SUBSCRIPTION_NONCE_FIELD = 'bizupkeep_bookkeeping_suspend_subscription_nonce';

    private const REACTIVATE_SUBSCRIPTION_NONCE_ACTION = 'bizupkeep_bookkeeping_reactivate_subscription';

    private const REACTIVATE_SUBSCRIPTION_NONCE_FIELD = 'bizupkeep_bookkeeping_reactivate_subscription_nonce';

    private const VAT_SETTINGS_NONCE_ACTION = 'bizupkeep_bookkeeping_update_vat_settings';

    private const VAT_SETTINGS_NONCE_FIELD = 'bizupkeep_bookkeeping_update_vat_settings_nonce';

    /**
     * Fixed number of line rows rendered. This is a staff-only internal
     * tool serving a small number of monthly adjustments, so a dynamic
     * JS add/remove-row widget isn't worth building for v1 - unused
     * rows are simply left blank and ignored on submit.
     */
    private const MAX_LINES = 8;

    private const RECENT_ENTRIES_LIMIT = 25;

    public function __construct(
        private readonly LedgerServiceInterface $ledger,
        private readonly AccountServiceInterface $accounts,
        private readonly JournalRepositoryInterface $journal,
        private readonly CompanyServiceInterface $companies,
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    /**
     * Render the page. Registered as the admin_menu callback for
     * self::SLUG.
     */
    public function render(): void
    {
        $userId = get_current_user_id();

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-bookkeeping'));
        }

        $notice = $this->handlePostEntry($userId)
            ?? $this->handleReverse($userId)
            ?? $this->handleExtendSubscription($userId)
            ?? $this->handleSuspendSubscription($userId)
            ?? $this->handleReactivateSubscription($userId)
            ?? $this->handleUpdateVatSettings($userId);

        echo '<div class="wrap"><h1>' . esc_html__('Bookkeeping', 'bizupkeep-bookkeeping') . '</h1>';

        if ($notice !== null) {
            [$type, $message] = $notice;
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>'
                . esc_html($message) . '</p></div>';
        }

        $company = $this->resolveCompanyFromRequest();

        $this->renderCompanyPicker($company);

        if ($company !== null) {
            $this->accounts->ensureSeeded($company->getUuid());
            $this->renderSubscriptionPanel($company);
            $this->renderVatSettingsPanel($company);
            $this->renderEntryForm($company);
            $this->renderRecentEntries($company);
        }

        echo '</div>';
    }

    private function resolveCompanyFromRequest(): ?Company
    {
        $uuid = $this->getParam('company');

        if ($uuid === '') {
            return null;
        }

        try {
            return $this->companies->getCompany($uuid);
        } catch (CompanyNotFoundException) {
            return null;
        }
    }

    /**
     * Generic sanitized-GET-param reader. This page's only GET-driven
     * action is the read-only company picker, which has no nonce (it
     * doesn't mutate anything).
     */
    private function getParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only company picker, no mutation.
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    /**
     * Generic sanitized-POST-param reader. Every caller that uses this
     * for a mutating action (handlePostEntry(), handleReverse()) always
     * verifies its own nonce first, before reading any other field via
     * this method - see those methods for the actual nonce checks.
     */
    private function postParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the caller before this is used.
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function renderCompanyPicker(?Company $company): void
    {
        echo '<form method="get" style="margin-bottom:1.5em;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '" />';
        echo '<label for="bizupkeep-bookkeeping-company">'
            . esc_html__('Company UUID', 'bizupkeep-bookkeeping') . '</label> ';
        echo '<input type="text" id="bizupkeep-bookkeeping-company" name="company" value="'
            . esc_attr($company?->getUuid() ?? $this->getParam('company')) . '" size="40" /> ';
        submit_button(__('Load', 'bizupkeep-bookkeeping'), 'secondary', '', false);
        echo '</form>';

        if ($company !== null) {
            echo '<p><strong>' . esc_html($company->getCompanyName()) . '</strong> ('
                . esc_html($company->getRegistrationNumber()) . ')</p>';
        } elseif ($this->getParam('company') !== '') {
            echo '<p>' . esc_html__('No company found with that UUID.', 'bizupkeep-bookkeeping') . '</p>';
        }
    }

    /**
     * Staff-only subscription management: shows the company's current
     * paid_until/active-inactive status plus three manual override
     * actions. Independent of the client-facing WooCommerce checkout
     * flow (astra-child's bizupkeep_child_handle_bookkeeping_subscription_order_payment())
     * - both ultimately call the same SubscriptionServiceInterface, so
     * a client payment and a staff override behave identically.
     */
    private function renderSubscriptionPanel(Company $company): void
    {
        $subscription = $this->subscriptions->getOrCreate($company->getUuid());

        echo '<h2>' . esc_html__('Subscription', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<p>';

        if ($subscription->isActive()) {
            echo esc_html__('Status: Active', 'bizupkeep-bookkeeping');

            if ($subscription->paidUntil !== null) {
                echo ' - ' . esc_html(sprintf(
                    /* translators: %s: the date the current subscription period runs until. */
                    __('paid until %s', 'bizupkeep-bookkeeping'),
                    $subscription->paidUntil->format('Y-m-d')
                ));
            }
        } elseif ($subscription->isSuspended) {
            echo esc_html__('Status: Suspended (manual override)', 'bizupkeep-bookkeeping');
        } else {
            echo esc_html__('Status: Inactive', 'bizupkeep-bookkeeping');
        }

        echo '</p>';

        echo '<form method="post" style="display:inline-block;margin-right:0.5em;">';
        wp_nonce_field(self::EXTEND_SUBSCRIPTION_NONCE_ACTION, self::EXTEND_SUBSCRIPTION_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_bookkeeping_action" value="extend_subscription" />';
        echo '<input type="hidden" name="company" value="' . esc_attr($company->getUuid()) . '" />';
        submit_button(__('Extend 30 Days', 'bizupkeep-bookkeeping'), 'secondary', '', false);
        echo '</form>';

        if ($subscription->isSuspended) {
            echo '<form method="post" style="display:inline-block;margin-right:0.5em;">';
            wp_nonce_field(self::REACTIVATE_SUBSCRIPTION_NONCE_ACTION, self::REACTIVATE_SUBSCRIPTION_NONCE_FIELD);
            echo '<input type="hidden" name="bizupkeep_bookkeeping_action" value="reactivate_subscription" />';
            echo '<input type="hidden" name="company" value="' . esc_attr($company->getUuid()) . '" />';
            submit_button(__('Reactivate', 'bizupkeep-bookkeeping'), 'secondary', '', false);
            echo '</form>';
        } else {
            echo '<form method="post" style="display:inline-block;">';
            wp_nonce_field(self::SUSPEND_SUBSCRIPTION_NONCE_ACTION, self::SUSPEND_SUBSCRIPTION_NONCE_FIELD);
            echo '<input type="hidden" name="bizupkeep_bookkeeping_action" value="suspend_subscription" />';
            echo '<input type="hidden" name="company" value="' . esc_attr($company->getUuid()) . '" />';
            submit_button(__('Suspend', 'bizupkeep-bookkeeping'), 'secondary', '', false);
            echo '</form>';
        }
    }

    /**
     * Staff-only VAT registration toggle: the sole switch controlling
     * whether the client-facing capture form (astra-child) shows VAT
     * fields at all, and whether TransactionCaptureService will accept
     * a VAT-inclusive capture for this company. Deliberately staff-set
     * only, never client-editable - see CompanySettingsRepositoryInterface.
     */
    private function renderVatSettingsPanel(Company $company): void
    {
        $settings = $this->companySettings->findByCompanyUuid($company->getUuid());

        echo '<h2>' . esc_html__('VAT', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<p>' . esc_html(
            $settings !== null && $settings->isVatRegistered
                ? __('Status: VAT Registered', 'bizupkeep-bookkeeping')
                : __('Status: Not VAT Registered', 'bizupkeep-bookkeeping')
        ) . '</p>';

        echo '<form method="post">';
        wp_nonce_field(self::VAT_SETTINGS_NONCE_ACTION, self::VAT_SETTINGS_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_bookkeeping_action" value="update_vat_settings" />';
        echo '<input type="hidden" name="company" value="' . esc_attr($company->getUuid()) . '" />';

        echo '<p><label><input type="checkbox" name="is_vat_registered" value="1"'
            . checked($settings?->isVatRegistered ?? false, true, false) . ' /> '
            . esc_html__('VAT Registered', 'bizupkeep-bookkeeping') . '</label></p>';

        echo '<p><label>' . esc_html__('VAT Number', 'bizupkeep-bookkeeping')
            . ' <input type="text" name="vat_number" value="' . esc_attr($settings?->vatNumber ?? '') . '" '
            . 'size="20" /></label></p>';

        submit_button(__('Save VAT Settings', 'bizupkeep-bookkeeping'), 'secondary', '', false);
        echo '</form>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleUpdateVatSettings(int $userId): ?array
    {
        if (! $this->isSubmittedAction('update_vat_settings')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::VAT_SETTINGS_NONCE_FIELD),
            self::VAT_SETTINGS_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            return ['error', __('You are not permitted to manage VAT settings.', 'bizupkeep-bookkeeping')];
        }

        $companyUuid = $this->postParam('company');
        $existing = $this->companySettings->findByCompanyUuid($companyUuid);
        $vatNumber = $this->postParam('vat_number');

        // Nonce already verified above; a checkbox's mere presence is the only signal needed here.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $isVatRegistered = isset($_POST['is_vat_registered']);

        $this->companySettings->save(new CompanySettings(
            uuid: $existing?->uuid ?? Uuid::generate(),
            companyUuid: $companyUuid,
            isVatRegistered: $isVatRegistered,
            vatNumber: $vatNumber !== '' ? $vatNumber : null,
            createdAt: $existing?->createdAt ?? new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        ));

        return ['success', __('VAT settings saved.', 'bizupkeep-bookkeeping')];
    }

    private function renderEntryForm(Company $company): void
    {
        $accountsList = $this->accounts->listAccounts($company->getUuid());

        echo '<h2>' . esc_html__('Post Manual Journal Entry', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_bookkeeping_action" value="post_entry" />';
        echo '<input type="hidden" name="company" value="' . esc_attr($company->getUuid()) . '" />';

        echo '<p><label>' . esc_html__('Date', 'bizupkeep-bookkeeping')
            . ' <input type="date" name="entry_date" value="' . esc_attr((new DateTimeImmutable())->format('Y-m-d'))
            . '" required /></label></p>';

        echo '<p><label>' . esc_html__('Description', 'bizupkeep-bookkeeping')
            . ' <input type="text" name="description" size="60" required /></label></p>';

        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Account', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Debit', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Credit', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Memo', 'bizupkeep-bookkeeping') . '</th>'
            . '</tr></thead><tbody>';

        for ($i = 0; $i < self::MAX_LINES; $i++) {
            echo '<tr><td><select name="lines[' . esc_attr((string) $i) . '][account]">';
            echo '<option value="">' . esc_html__('-- none --', 'bizupkeep-bookkeeping') . '</option>';

            foreach ($accountsList as $account) {
                /** @var Account $account */
                echo '<option value="' . esc_attr($account->uuid) . '">'
                    . esc_html($account->code . ' - ' . $account->name) . '</option>';
            }

            echo '</select></td>';
            echo '<td><input type="number" step="0.01" min="0" '
                . 'name="lines[' . esc_attr((string) $i) . '][debit]" /></td>';
            echo '<td><input type="number" step="0.01" min="0" '
                . 'name="lines[' . esc_attr((string) $i) . '][credit]" /></td>';
            echo '<td><input type="text" name="lines[' . esc_attr((string) $i) . '][memo]" size="20" /></td></tr>';
        }

        echo '</tbody></table>';
        submit_button(__('Post Entry', 'bizupkeep-bookkeeping'));
        echo '</form>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handlePostEntry(int $userId): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_bookkeeping_action') !== 'post_entry') {
            return null;
        }

        if (! wp_verify_nonce($this->postParam(self::NONCE_FIELD), self::NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            return ['error', __('You are not permitted to post journal entries.', 'bizupkeep-bookkeeping')];
        }

        $companyUuid = $this->postParam('company');
        $description = $this->postParam('description');
        $entryDate = $this->postParam('entry_date');

        $lines = $this->parseLines();

        try {
            $this->ledger->postEntry(
                new ManualJournalEntryData(
                    companyUuid: $companyUuid,
                    date: new DateTimeImmutable($entryDate !== '' ? $entryDate : 'now'),
                    description: $description,
                    lines: $lines,
                    createdBy: $userId,
                ),
                JournalSource::Manual
            );

            return ['success', __('Journal entry posted.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    /**
     * Nonce verification for this POST happens in the caller
     * (handlePostEntry(), before this method is ever invoked) - this
     * private helper only parses $_POST['lines'], it doesn't process
     * the request on its own.
     *
     * @return JournalLineData[]
     */
    private function parseLines(): array
    {
        // Nonce is verified in handlePostEntry() before this is called; an array can't go through
        // sanitize_text_field() directly, every string it contains is sanitized per-field below.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw = isset($_POST['lines']) ? wp_unslash($_POST['lines']) : [];

        if (! is_array($raw)) {
            return [];
        }

        $lines = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $accountUuid = isset($row['account']) ? sanitize_text_field((string) $row['account']) : '';

            if ($accountUuid === '') {
                continue;
            }

            $debit = isset($row['debit']) ? (float) sanitize_text_field((string) $row['debit']) : 0.0;
            $credit = isset($row['credit']) ? (float) sanitize_text_field((string) $row['credit']) : 0.0;
            $memo = isset($row['memo']) ? sanitize_text_field((string) $row['memo']) : '';

            if ($debit > 0) {
                $lines[] = JournalLineData::debit($accountUuid, Money::fromRands($debit), $memo);
            } elseif ($credit > 0) {
                $lines[] = JournalLineData::credit($accountUuid, Money::fromRands($credit), $memo);
            }
        }

        return $lines;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleReverse(int $userId): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_bookkeeping_action') !== 'reverse_entry') {
            return null;
        }

        if (! wp_verify_nonce($this->postParam(self::REVERSE_NONCE_FIELD), self::REVERSE_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            return ['error', __('You are not permitted to reverse journal entries.', 'bizupkeep-bookkeeping')];
        }

        $entryUuid = $this->postParam('entry');
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        try {
            $this->ledger->reverseEntry($entryUuid, $userId, $reason);

            return ['success', __('Journal entry reversed.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleExtendSubscription(int $userId): ?array
    {
        if (! $this->isSubmittedAction('extend_subscription')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::EXTEND_SUBSCRIPTION_NONCE_FIELD),
            self::EXTEND_SUBSCRIPTION_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            return ['error', __('You are not permitted to manage subscriptions.', 'bizupkeep-bookkeeping')];
        }

        $this->subscriptions->extend($this->postParam('company'), 30);

        return ['success', __('Subscription extended by 30 days.', 'bizupkeep-bookkeeping')];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleSuspendSubscription(int $userId): ?array
    {
        if (! $this->isSubmittedAction('suspend_subscription')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::SUSPEND_SUBSCRIPTION_NONCE_FIELD),
            self::SUSPEND_SUBSCRIPTION_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            return ['error', __('You are not permitted to manage subscriptions.', 'bizupkeep-bookkeeping')];
        }

        $this->subscriptions->suspend($this->postParam('company'));

        return ['success', __('Subscription suspended.', 'bizupkeep-bookkeeping')];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleReactivateSubscription(int $userId): ?array
    {
        if (! $this->isSubmittedAction('reactivate_subscription')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::REACTIVATE_SUBSCRIPTION_NONCE_FIELD),
            self::REACTIVATE_SUBSCRIPTION_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            return ['error', __('You are not permitted to manage subscriptions.', 'bizupkeep-bookkeeping')];
        }

        $this->subscriptions->reactivate($this->postParam('company'));

        return ['success', __('Subscription reactivated.', 'bizupkeep-bookkeeping')];
    }

    private function renderRecentEntries(Company $company): void
    {
        $range = DateRange::sinceInception(new DateTimeImmutable());
        $entries = $this->journal->findEntriesForCompany($company->getUuid(), $range, self::RECENT_ENTRIES_LIMIT);

        echo '<h2>' . esc_html__('Recent Entries', 'bizupkeep-bookkeeping') . '</h2>';

        if ($entries === []) {
            echo '<p>' . esc_html__('No journal entries yet.', 'bizupkeep-bookkeeping') . '</p>';

            return;
        }

        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Date', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Description', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Source', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Amount', 'bizupkeep-bookkeeping') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            $total = Money::zero();

            foreach ($entry->lines as $line) {
                if ($line->isDebit()) {
                    $total = $total->add($line->debit);
                }
            }

            $alreadyReversed = $this->journal->findReversalOf($entry->uuid) !== null;

            echo '<tr><td>' . esc_html($entry->entryDate->format('Y-m-d')) . '</td>';
            echo '<td>' . esc_html($entry->description) . '</td>';
            echo '<td>' . esc_html($entry->source->label()) . '</td>';
            echo '<td>' . esc_html($total->format()) . '</td>';
            echo '<td>';

            if ($entry->isReversal()) {
                echo esc_html__('Reversal', 'bizupkeep-bookkeeping');
            } elseif ($alreadyReversed) {
                echo esc_html__('Reversed', 'bizupkeep-bookkeeping');
            } else {
                echo '<form method="post" style="display:inline;" onsubmit="return confirm(\''
                    . esc_js(__('Reverse this entry?', 'bizupkeep-bookkeeping')) . '\');">';
                wp_nonce_field(self::REVERSE_NONCE_ACTION, self::REVERSE_NONCE_FIELD);
                echo '<input type="hidden" name="bizupkeep_bookkeeping_action" value="reverse_entry" />';
                echo '<input type="hidden" name="company" value="' . esc_attr($company->getUuid()) . '" />';
                echo '<input type="hidden" name="entry" value="' . esc_attr($entry->uuid) . '" />';
                submit_button(__('Reverse', 'bizupkeep-bookkeeping'), 'small', '', false);
                echo '</form>';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function requestMethod(): string
    {
        return isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    }

    private function isSubmittedAction(string $action): bool
    {
        return $this->requestMethod() === 'POST' && $this->postParam('bizupkeep_bookkeeping_action') === $action;
    }
}
