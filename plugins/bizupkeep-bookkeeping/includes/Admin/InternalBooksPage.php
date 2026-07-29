<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Admin;

use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Contracts\FinancialStatementsServiceInterface;
use BizHub\Bookkeeping\Contracts\RecurringTransactionServiceInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\Contracts\TransactionCaptureServiceInterface;
use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\DTO\DateRange;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\TransactionType;
use BizHub\Bookkeeping\Exceptions\BookkeepingException;
use BizHub\Bookkeeping\Export\QuickBooksOnlineExporter;
use BizHub\Bookkeeping\Export\SageExporter;
use BizHub\Bookkeeping\Export\XeroExporter;
use BizHub\Bookkeeping\Policies\Capabilities;
use BizHub\Bookkeeping\Support\Money;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\DTO\AddressData;
use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Framework\Support\Uuid;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use DateTimeImmutable;

/**
 * Staff-facing "Internal Books" admin screen: gives A2Z Business
 * Administrators the exact same bookkeeping feature set a client
 * company gets (Capture, Recurring, Chart of Accounts, Statements,
 * Export), scoped permanently to A2Z's own company - there is no
 * "platform owner" concept anywhere else in the system, so the first
 * visit to this page lazily creates one real Client + Company record
 * for A2Z through the standard BizHub core APIs, exactly like any
 * other company, and remembers its UUID in a WP option.
 *
 * Every tab is a thin wrapper over the same interfaces the client
 * portal and ManualJournalEntryPage already call - no new posting
 * logic exists anywhere in this class.
 *
 * @package BizHub\Bookkeeping\Admin
 */
final class InternalBooksPage
{
    public const SLUG = 'bizupkeep-bookkeeping-internal';

    public const EXPORT_ACTION = 'bizupkeep_bookkeeping_internal_export';

    private const OPTION_COMPANY_UUID = 'bizupkeep_bookkeeping_internal_company_uuid';

    /**
     * Extends A2Z's own subscription far enough into the future that
     * TransactionCaptureService's subscription gate never needs a
     * special case for internal use - staff never has to "renew"
     * A2Z's own access.
     */
    private const SUBSCRIPTION_EXTEND_DAYS = 36500;

    private const SETUP_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_setup';

    private const SETUP_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_setup_nonce';

    private const CAPTURE_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_capture';

    private const CAPTURE_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_capture_nonce';

    private const RECURRING_CREATE_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_recurring_create';

    private const RECURRING_CREATE_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_recurring_create_nonce';

    private const RECURRING_CONFIRM_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_recurring_confirm';

    private const RECURRING_CONFIRM_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_recurring_confirm_nonce';

    private const RECURRING_CONFIRM_ALL_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_recurring_confirm_all';

    private const RECURRING_CONFIRM_ALL_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_recurring_confirm_all_nonce';

    private const RECURRING_SKIP_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_recurring_skip';

    private const RECURRING_SKIP_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_recurring_skip_nonce';

    private const RECURRING_TOGGLE_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_recurring_toggle';

    private const RECURRING_TOGGLE_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_recurring_toggle_nonce';

    private const RECURRING_DELETE_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_recurring_delete';

    private const RECURRING_DELETE_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_recurring_delete_nonce';

    private const EXPORT_NONCE_ACTION = 'bizupkeep_bookkeeping_internal_export';

    private const EXPORT_NONCE_FIELD = 'bizupkeep_bookkeeping_internal_export_nonce';

    private const TABS = ['capture', 'recurring', 'accounts', 'statements', 'export'];

    public function __construct(
        private readonly ClientServiceInterface $clientService,
        private readonly CompanyServiceInterface $companies,
        private readonly AccountServiceInterface $accounts,
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly TransactionCaptureServiceInterface $capture,
        private readonly RecurringTransactionServiceInterface $recurring,
        private readonly FinancialStatementsServiceInterface $statements,
        private readonly QuickBooksOnlineExporter $quickBooksExporter,
        private readonly XeroExporter $xeroExporter,
        private readonly SageExporter $sageExporter,
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

        echo '<div class="wrap"><h1>' . esc_html__('Internal Books', 'bizupkeep-bookkeeping') . '</h1>';

        $company = $this->resolveInternalCompany();

        if ($company === null) {
            $notice = $this->handleSetup($userId);
            $this->renderNotice($notice);
            $this->renderSetupForm();
            echo '</div>';

            return;
        }

        $this->accounts->ensureSeeded($company->getUuid());

        $notice = $this->handleCapture($userId, $company)
            ?? $this->handleRecurringCreate($userId, $company)
            ?? $this->handleRecurringConfirm($userId, $company)
            ?? $this->handleRecurringConfirmAll($userId, $company)
            ?? $this->handleRecurringSkip($userId, $company)
            ?? $this->handleRecurringToggle($userId, $company)
            ?? $this->handleRecurringDelete($userId, $company);

        $this->renderNotice($notice);
        $this->renderTabsNav($company);

        $tab = $this->currentTab();

        match ($tab) {
            'recurring' => $this->renderRecurringTab($company),
            'accounts' => $this->renderAccountsTab($company),
            'statements' => $this->renderStatementsTab($company),
            'export' => $this->renderExportTab($company),
            default => $this->renderCaptureTab($company),
        };

        echo '</div>';
    }

    /**
     * Registered as the admin_post_{self::EXPORT_ACTION} callback -
     * runs before any HTML is sent, the only place in wp-admin a raw
     * file download can be streamed from.
     */
    public function handleExport(): void
    {
        $userId = get_current_user_id();

        if (! $this->authorization->can($userId, Capabilities::BOOKKEEPING_MANAGE)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-bookkeeping'));
        }

        check_admin_referer(self::EXPORT_NONCE_ACTION, self::EXPORT_NONCE_FIELD);

        $company = $this->resolveInternalCompany();

        if ($company === null) {
            wp_safe_redirect($this->pageUrl());
            exit;
        }

        $platform = $this->getParam('platform');

        $exporter = match ($platform) {
            'quickbooks' => $this->quickBooksExporter,
            'xero' => $this->xeroExporter,
            'sage' => $this->sageExporter,
            default => null,
        };

        if ($exporter === null) {
            wp_safe_redirect($this->pageUrl(['tab' => 'export', 'internal_error' => '1']));
            exit;
        }

        $range = $this->parseRange();
        $csv = $exporter->exportJournalEntries($company->getUuid(), $range);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header(
            'Content-Disposition: attachment; filename="'
            . sanitize_file_name($platform . '-internal-export-' . gmdate('Y-m-d') . '.csv') . '"'
        );
        header('Content-Length: ' . strlen($csv));

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSV file content, not HTML.
        echo $csv;
        exit;
    }

    /**
     * Resolves A2Z's own company from the stored option, clearing it
     * and falling back to the setup flow if it points at a company
     * that no longer exists (e.g. deleted by hand).
     */
    private function resolveInternalCompany(): ?Company
    {
        $companyUuid = get_option(self::OPTION_COMPANY_UUID);

        if (! is_string($companyUuid) || $companyUuid === '') {
            return null;
        }

        try {
            return $this->companies->getCompany($companyUuid);
        } catch (CompanyNotFoundException) {
            delete_option(self::OPTION_COMPANY_UUID);

            return null;
        }
    }

    private function renderSetupForm(): void
    {
        echo '<h2>' . esc_html__('Set Up Internal Books', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<p>' . esc_html__(
            'Creates one company record for the business itself, tracked with the exact same tools every client gets.',
            'bizupkeep-bookkeeping'
        ) . '</p>';

        echo '<form method="post">';
        wp_nonce_field(self::SETUP_NONCE_ACTION, self::SETUP_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="setup" />';

        echo '<table class="form-table"><tbody>';
        $this->renderTextField(
            'company_name',
            __('Company Name', 'bizupkeep-bookkeeping'),
            'A2Z Business Administrators'
        );
        $this->renderTextField('registration_number', __('Registration Number', 'bizupkeep-bookkeeping'), '');
        $this->renderTextField('address_line_1', __('Address Line 1', 'bizupkeep-bookkeeping'), '');
        $this->renderTextField('address_line_2', __('Address Line 2', 'bizupkeep-bookkeeping'), '');
        $this->renderTextField('suburb', __('Suburb', 'bizupkeep-bookkeeping'), '');
        $this->renderTextField('city', __('City', 'bizupkeep-bookkeeping'), '');
        $this->renderTextField('province', __('Province', 'bizupkeep-bookkeeping'), '');
        $this->renderTextField('postal_code', __('Postal Code', 'bizupkeep-bookkeeping'), '');
        echo '</tbody></table>';

        submit_button(__('Set Up Internal Books', 'bizupkeep-bookkeeping'));
        echo '</form>';
    }

    private function renderTextField(string $name, string $label, string $default): void
    {
        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-' . esc_attr($name) . '">'
            . esc_html($label) . '</label></th><td>';
        echo '<input type="text" id="bizupkeep-bk-internal-' . esc_attr($name) . '" name="' . esc_attr($name)
            . '" value="' . esc_attr($default) . '" class="regular-text" /></td></tr>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleSetup(int $userId): ?array
    {
        if (! $this->isSubmittedAction('setup')) {
            return null;
        }

        if (! wp_verify_nonce($this->postParam(self::SETUP_NONCE_FIELD), self::SETUP_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        $companyName = $this->postParam('company_name');
        $registrationNumber = $this->postParam('registration_number');

        if ($companyName === '' || $registrationNumber === '') {
            return ['error', __('Company name and registration number are required.', 'bizupkeep-bookkeeping')];
        }

        $client = $this->resolveOrCreateStaffClient($userId);

        $companyUuid = Uuid::generate();

        $this->companies->createCompany(new CompanyData(
            uuid: $companyUuid,
            clientId: $client->getId() ?? 0,
            registrationNumber: $registrationNumber,
            companyName: $companyName,
            companyType: 'Private Company',
            status: CompanyStatus::ACTIVE,
            registeredAddress: new AddressData(
                addressLine1: $this->postParam('address_line_1'),
                addressLine2: $this->postParam('address_line_2'),
                suburb: $this->postParam('suburb'),
                city: $this->postParam('city'),
                province: $this->postParam('province'),
                postalCode: $this->postParam('postal_code'),
            ),
        ));

        $this->accounts->ensureSeeded($companyUuid);
        $this->subscriptions->extend($companyUuid, self::SUBSCRIPTION_EXTEND_DAYS);

        update_option(self::OPTION_COMPANY_UUID, $companyUuid);

        return ['success', __('Internal Books set up. Refresh to continue.', 'bizupkeep-bookkeeping')];
    }

    /**
     * A2Z's own company needs a real owning Client record (BizHub core
     * has no "platform owner" concept) - reuse the setting-up staff
     * user's existing client account if they happen to already have
     * one, otherwise create one tied to their own WP user ID.
     */
    private function resolveOrCreateStaffClient(int $userId): Client
    {
        try {
            return $this->clientService->getClientByWpUserId($userId);
        } catch (ClientNotFoundException) {
            // Falls through to creation below.
        }

        $this->clientService->createClient(new ClientData(
            uuid: Uuid::generate(),
            wpUserId: $userId,
            profile: new ProfileData(
                firstName: __('Internal', 'bizupkeep-bookkeeping'),
                lastName: __('Books', 'bizupkeep-bookkeeping'),
            ),
            status: ClientStatus::ACTIVE,
        ));

        // ClientRepository::save() does not hydrate the numeric id back
        // onto the entity it was given - re-fetch to get it.
        return $this->clientService->getClientByWpUserId($userId);
    }

    private function renderTabsNav(Company $company): void
    {
        echo '<p><strong>' . esc_html($company->getCompanyName()) . '</strong> ('
            . esc_html($company->getRegistrationNumber()) . ')</p>';

        $labels = [
            'capture' => __('Capture', 'bizupkeep-bookkeeping'),
            'recurring' => __('Recurring', 'bizupkeep-bookkeeping'),
            'accounts' => __('Chart of Accounts', 'bizupkeep-bookkeeping'),
            'statements' => __('Statements', 'bizupkeep-bookkeeping'),
            'export' => __('Export', 'bizupkeep-bookkeeping'),
        ];

        $current = $this->currentTab();

        echo '<h2 class="nav-tab-wrapper">';

        foreach ($labels as $key => $label) {
            $class = $key === $current ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="' . esc_url($this->pageUrl(['tab' => $key])) . '" class="' . esc_attr($class) . '">'
                . esc_html($label) . '</a>';
        }

        echo '</h2>';
    }

    private function renderCaptureTab(Company $company): void
    {
        $incomeAccounts = $this->accounts->listIncomeAccounts($company->getUuid());
        $expenseAccounts = $this->accounts->listExpenseAccounts($company->getUuid());
        $vatRegistered = $this->companySettings->findByCompanyUuid($company->getUuid())?->isVatRegistered ?? false;

        echo '<h2>' . esc_html__('Capture Income / Expense', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field(self::CAPTURE_NONCE_ACTION, self::CAPTURE_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="capture" />';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Type', 'bizupkeep-bookkeeping') . '</th><td>';
        echo '<label><input type="radio" name="transaction_type" value="income" checked /> '
            . esc_html__('Income', 'bizupkeep-bookkeeping') . '</label> ';
        echo '<label><input type="radio" name="transaction_type" value="expense" /> '
            . esc_html__('Expense', 'bizupkeep-bookkeeping') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-date">'
            . esc_html__('Date', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<input type="date" id="bizupkeep-bk-internal-date" name="transaction_date" value="'
            . esc_attr((new DateTimeImmutable())->format('Y-m-d')) . '" required /></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-amount">'
            . esc_html__('Amount (R)', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<input type="number" step="0.01" min="0.01" id="bizupkeep-bk-internal-amount" name="amount" required />'
            . '</td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-category">'
            . esc_html__('Category', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<select id="bizupkeep-bk-internal-category" name="category_account">';
        echo '<optgroup label="' . esc_attr__('Income', 'bizupkeep-bookkeeping') . '">';

        foreach ($incomeAccounts as $account) {
            echo '<option value="' . esc_attr($account->uuid) . '">' . esc_html($account->name) . '</option>';
        }

        echo '</optgroup><optgroup label="' . esc_attr__('Expense', 'bizupkeep-bookkeeping') . '">';

        foreach ($expenseAccounts as $account) {
            echo '<option value="' . esc_attr($account->uuid) . '">' . esc_html($account->name) . '</option>';
        }

        echo '</optgroup></select></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-payment">'
            . esc_html__('Payment Method', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<select id="bizupkeep-bk-internal-payment" name="payment_method">'
            . '<option value="bank">' . esc_html__('Bank', 'bizupkeep-bookkeeping') . '</option>'
            . '<option value="cash">' . esc_html__('Cash', 'bizupkeep-bookkeeping') . '</option>'
            . '</select></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-description">'
            . esc_html__('Description', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<input type="text" id="bizupkeep-bk-internal-description" name="description" class="regular-text" '
            . 'maxlength="500" required /></td></tr>';

        if ($vatRegistered) {
            echo '<tr><th scope="row">' . esc_html__('VAT', 'bizupkeep-bookkeeping') . '</th><td>';
            echo '<label><input type="checkbox" name="includes_vat" value="1" /> '
                . esc_html__('This amount includes VAT (15%)', 'bizupkeep-bookkeeping') . '</label></td></tr>';
        }

        echo '</tbody></table>';
        submit_button(__('Capture Transaction', 'bizupkeep-bookkeeping'));
        echo '</form>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleCapture(int $userId, Company $company): ?array
    {
        if (! $this->isSubmittedAction('capture')) {
            return null;
        }

        if (! wp_verify_nonce($this->postParam(self::CAPTURE_NONCE_FIELD), self::CAPTURE_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        $vatRegistered = $this->companySettings->findByCompanyUuid($company->getUuid())?->isVatRegistered ?? false;

        // Nonce already verified above; a checkbox's mere presence is the only signal needed here.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $includesVat = isset($_POST['includes_vat']) && $vatRegistered;

        $paymentMethod = $this->postParam('payment_method') === 'cash' ? PaymentMethod::Cash : PaymentMethod::Bank;
        $dateRaw = $this->postParam('transaction_date');

        try {
            $date = $dateRaw !== '' ? new DateTimeImmutable($dateRaw) : new DateTimeImmutable();
        } catch (\Exception) {
            return ['error', __('Invalid date.', 'bizupkeep-bookkeeping')];
        }

        $data = new CaptureTransactionData(
            date: $date,
            amount: Money::fromRands((float) $this->postParam('amount')),
            categoryAccountUuid: $this->postParam('category_account'),
            paymentMethod: $paymentMethod,
            description: $this->postParam('description'),
            includesVat: $includesVat,
        );

        try {
            if ($this->postParam('transaction_type') === 'income') {
                $this->capture->captureIncome($company->getUuid(), $data, $userId);
            } else {
                $this->capture->captureExpense($company->getUuid(), $data, $userId);
            }

            return ['success', __('Transaction captured.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    private function renderRecurringTab(Company $company): void
    {
        $this->renderRecurringPendingSection($company);
        $this->renderRecurringTemplatesSection($company);
    }

    private function renderRecurringPendingSection(Company $company): void
    {
        $pending = $this->recurring->listPendingOccurrences($company->getUuid());

        echo '<h2>' . esc_html__('Pending Review', 'bizupkeep-bookkeeping') . '</h2>';

        if ($pending === []) {
            echo '<p>' . esc_html__('No recurring transactions awaiting review.', 'bizupkeep-bookkeeping') . '</p>';

            return;
        }

        $templatesByUuid = [];

        foreach ($this->recurring->listTemplates($company->getUuid()) as $template) {
            $templatesByUuid[$template->uuid] = $template;
        }

        $bulkFormId = 'bizupkeep-bk-internal-recurring-confirm-all';

        echo '<table class="widefat"><thead><tr>'
            . '<th></th><th>' . esc_html__('Due Date', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Description', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Amount', 'bizupkeep-bookkeeping') . '</th>'
            . '<th></th></tr></thead><tbody>';

        foreach ($pending as $occurrence) {
            $template = $templatesByUuid[$occurrence->templateUuid] ?? null;

            if ($template === null) {
                continue;
            }

            echo '<tr>';
            echo '<td><input type="checkbox" name="occurrence[]" value="' . esc_attr($occurrence->uuid)
                . '" form="' . esc_attr($bulkFormId) . '" /></td>';

            echo '<td><form method="post" style="display:inline;">';
            wp_nonce_field(self::RECURRING_CONFIRM_NONCE_ACTION, self::RECURRING_CONFIRM_NONCE_FIELD);
            echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="recurring_confirm" />';
            echo '<input type="hidden" name="occurrence" value="' . esc_attr($occurrence->uuid) . '" />';
            echo '<input type="date" name="due_date" value="'
                . esc_attr($occurrence->dueDate->format('Y-m-d')) . '" />';
            echo '</td>';

            echo '<td>' . esc_html($template->description) . '</td>';

            echo '<td><input type="number" step="0.01" min="0.01" name="amount" value="'
                . esc_attr(number_format($template->amount->toRands(), 2, '.', '')) . '" /></td>';

            echo '<td>';
            submit_button(__('Confirm & Post', 'bizupkeep-bookkeeping'), 'secondary', '', false);
            echo '</form>';

            echo '<form method="post" style="display:inline;" onsubmit="return confirm(\''
                . esc_js(__('Skip this occurrence?', 'bizupkeep-bookkeeping')) . '\');">';
            wp_nonce_field(self::RECURRING_SKIP_NONCE_ACTION, self::RECURRING_SKIP_NONCE_FIELD);
            echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="recurring_skip" />';
            echo '<input type="hidden" name="occurrence" value="' . esc_attr($occurrence->uuid) . '" />';
            submit_button(__('Skip', 'bizupkeep-bookkeeping'), 'secondary', '', false);
            echo '</form></td>';

            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<form method="post" id="' . esc_attr($bulkFormId) . '">';
        wp_nonce_field(self::RECURRING_CONFIRM_ALL_NONCE_ACTION, self::RECURRING_CONFIRM_ALL_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="recurring_confirm_all" />';
        echo '<p>';
        submit_button(__('Confirm All Selected As-Is', 'bizupkeep-bookkeeping'), 'primary', '', false);
        echo '</p></form>';
    }

    private function renderRecurringTemplatesSection(Company $company): void
    {
        $templates = $this->recurring->listTemplates($company->getUuid());

        echo '<h2>' . esc_html__('Recurring Templates', 'bizupkeep-bookkeeping') . '</h2>';

        if ($templates !== []) {
            echo '<table class="widefat"><thead><tr>'
                . '<th>' . esc_html__('Type', 'bizupkeep-bookkeeping') . '</th>'
                . '<th>' . esc_html__('Amount', 'bizupkeep-bookkeeping') . '</th>'
                . '<th>' . esc_html__('Description', 'bizupkeep-bookkeeping') . '</th>'
                . '<th>' . esc_html__('Frequency', 'bizupkeep-bookkeeping') . '</th>'
                . '<th>' . esc_html__('Next Due', 'bizupkeep-bookkeeping') . '</th>'
                . '<th>' . esc_html__('Status', 'bizupkeep-bookkeeping') . '</th>'
                . '<th></th></tr></thead><tbody>';

            foreach ($templates as $template) {
                echo '<tr>';
                echo '<td>' . esc_html(
                    $template->transactionType === TransactionType::Income
                        ? __('Income', 'bizupkeep-bookkeeping')
                        : __('Expense', 'bizupkeep-bookkeeping')
                ) . '</td>';
                echo '<td>' . esc_html($template->amount->format()) . '</td>';
                echo '<td>' . esc_html($template->description) . '</td>';
                echo '<td>' . esc_html($template->frequency->label()) . '</td>';
                echo '<td>' . esc_html($template->nextDueDate->format('Y-m-d')) . '</td>';
                echo '<td>' . esc_html(
                    $template->isActive ? __('Active', 'bizupkeep-bookkeeping') : __('Paused', 'bizupkeep-bookkeeping')
                ) . '</td>';

                echo '<td>';
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field(self::RECURRING_TOGGLE_NONCE_ACTION, self::RECURRING_TOGGLE_NONCE_FIELD);
                echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="recurring_toggle" />';
                echo '<input type="hidden" name="template" value="' . esc_attr($template->uuid) . '" />';
                echo '<input type="hidden" name="active" value="' . esc_attr($template->isActive ? '0' : '1') . '" />';
                submit_button(
                    $template->isActive ? __('Pause', 'bizupkeep-bookkeeping') : __('Resume', 'bizupkeep-bookkeeping'),
                    'secondary',
                    '',
                    false
                );
                echo '</form>';

                echo '<form method="post" style="display:inline;" onsubmit="return confirm(\''
                    . esc_js(__('Delete this recurring template?', 'bizupkeep-bookkeeping')) . '\');">';
                wp_nonce_field(self::RECURRING_DELETE_NONCE_ACTION, self::RECURRING_DELETE_NONCE_FIELD);
                echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="recurring_delete" />';
                echo '<input type="hidden" name="template" value="' . esc_attr($template->uuid) . '" />';
                submit_button(__('Delete', 'bizupkeep-bookkeeping'), 'secondary', '', false);
                echo '</form>';
                echo '</td></tr>';
            }

            echo '</tbody></table>';
        }

        $this->renderRecurringNewTemplateForm($company);
    }

    private function renderRecurringNewTemplateForm(Company $company): void
    {
        $incomeAccounts = $this->accounts->listIncomeAccounts($company->getUuid());
        $expenseAccounts = $this->accounts->listExpenseAccounts($company->getUuid());
        $vatRegistered = $this->companySettings->findByCompanyUuid($company->getUuid())?->isVatRegistered ?? false;

        echo '<h3>' . esc_html__('New Recurring Transaction', 'bizupkeep-bookkeeping') . '</h3>';
        echo '<form method="post">';
        wp_nonce_field(self::RECURRING_CREATE_NONCE_ACTION, self::RECURRING_CREATE_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_bookkeeping_internal_action" value="recurring_create" />';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Type', 'bizupkeep-bookkeeping') . '</th><td>';
        echo '<label><input type="radio" name="transaction_type" value="income" checked /> '
            . esc_html__('Income', 'bizupkeep-bookkeeping') . '</label> ';
        echo '<label><input type="radio" name="transaction_type" value="expense" /> '
            . esc_html__('Expense', 'bizupkeep-bookkeeping') . '</label></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-rec-amount">'
            . esc_html__('Amount (R)', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<input type="number" step="0.01" min="0.01" id="bizupkeep-bk-internal-rec-amount" name="amount" '
            . 'required /></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-rec-category">'
            . esc_html__('Category', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<select id="bizupkeep-bk-internal-rec-category" name="category_account">';
        echo '<optgroup label="' . esc_attr__('Income', 'bizupkeep-bookkeeping') . '">';

        foreach ($incomeAccounts as $account) {
            echo '<option value="' . esc_attr($account->uuid) . '">' . esc_html($account->name) . '</option>';
        }

        echo '</optgroup><optgroup label="' . esc_attr__('Expense', 'bizupkeep-bookkeeping') . '">';

        foreach ($expenseAccounts as $account) {
            echo '<option value="' . esc_attr($account->uuid) . '">' . esc_html($account->name) . '</option>';
        }

        echo '</optgroup></select></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-rec-payment">'
            . esc_html__('Payment Method', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<select id="bizupkeep-bk-internal-rec-payment" name="payment_method">'
            . '<option value="bank">' . esc_html__('Bank', 'bizupkeep-bookkeeping') . '</option>'
            . '<option value="cash">' . esc_html__('Cash', 'bizupkeep-bookkeeping') . '</option>'
            . '</select></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-rec-description">'
            . esc_html__('Description', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<input type="text" id="bizupkeep-bk-internal-rec-description" name="description" class="regular-text" '
            . 'maxlength="500" required /></td></tr>';

        if ($vatRegistered) {
            echo '<tr><th scope="row">' . esc_html__('VAT', 'bizupkeep-bookkeeping') . '</th><td>';
            echo '<label><input type="checkbox" name="includes_vat" value="1" /> '
                . esc_html__('This amount includes VAT (15%)', 'bizupkeep-bookkeeping') . '</label></td></tr>';
        }

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-rec-frequency">'
            . esc_html__('Frequency', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<select id="bizupkeep-bk-internal-rec-frequency" name="frequency">';

        foreach (RecurringFrequency::cases() as $frequency) {
            echo '<option value="' . esc_attr($frequency->value) . '" '
                . selected(RecurringFrequency::Monthly->value, $frequency->value, false) . '>'
                . esc_html($frequency->label()) . '</option>';
        }

        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="bizupkeep-bk-internal-rec-start">'
            . esc_html__('Start Date', 'bizupkeep-bookkeeping') . '</label></th><td>';
        echo '<input type="date" id="bizupkeep-bk-internal-rec-start" name="start_date" value="'
            . esc_attr((new DateTimeImmutable())->format('Y-m-d')) . '" required /></td></tr>';

        echo '</tbody></table>';
        submit_button(__('Create Recurring Transaction', 'bizupkeep-bookkeeping'));
        echo '</form>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleRecurringCreate(int $userId, Company $company): ?array
    {
        if (! $this->isSubmittedAction('recurring_create')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::RECURRING_CREATE_NONCE_FIELD),
            self::RECURRING_CREATE_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        $vatRegistered = $this->companySettings->findByCompanyUuid($company->getUuid())?->isVatRegistered ?? false;

        // Nonce already verified above; a checkbox's mere presence is the only signal needed here.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $includesVat = isset($_POST['includes_vat']) && $vatRegistered;

        $transactionType = $this->postParam('transaction_type') === 'income'
            ? TransactionType::Income
            : TransactionType::Expense;
        $paymentMethod = $this->postParam('payment_method') === 'cash' ? PaymentMethod::Cash : PaymentMethod::Bank;

        try {
            $frequency = RecurringFrequency::from($this->postParam('frequency'));
            $startDateRaw = $this->postParam('start_date');
            $startDate = $startDateRaw !== '' ? new DateTimeImmutable($startDateRaw) : new DateTimeImmutable();
        } catch (\Throwable) {
            return ['error', __('Invalid frequency or start date.', 'bizupkeep-bookkeeping')];
        }

        try {
            $this->recurring->createTemplate(
                $company->getUuid(),
                $transactionType,
                Money::fromRands((float) $this->postParam('amount')),
                $this->postParam('category_account'),
                $paymentMethod,
                $this->postParam('description'),
                $includesVat,
                $frequency,
                $startDate
            );

            return ['success', __('Recurring transaction created.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleRecurringConfirm(int $userId, Company $company): ?array
    {
        if (! $this->isSubmittedAction('recurring_confirm')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::RECURRING_CONFIRM_NONCE_FIELD),
            self::RECURRING_CONFIRM_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        $occurrenceUuid = $this->postParam('occurrence');
        $amountRaw = $this->postParam('amount');
        $dueDateRaw = $this->postParam('due_date');

        $overrideAmount = $amountRaw !== '' ? Money::fromRands((float) $amountRaw) : null;

        try {
            $overrideDate = $dueDateRaw !== '' ? new DateTimeImmutable($dueDateRaw) : null;
        } catch (\Exception) {
            $overrideDate = null;
        }

        try {
            $this->recurring->confirmOccurrence(
                $company->getUuid(),
                $occurrenceUuid,
                $userId,
                $overrideAmount,
                $overrideDate
            );

            return ['success', __('Recurring transaction confirmed and posted.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleRecurringConfirmAll(int $userId, Company $company): ?array
    {
        if (! $this->isSubmittedAction('recurring_confirm_all')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::RECURRING_CONFIRM_ALL_NONCE_FIELD),
            self::RECURRING_CONFIRM_ALL_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        // Nonce verified above; the array is only ever read as sanitized string UUIDs below.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $occurrenceUuids = isset($_POST['occurrence']) && is_array($_POST['occurrence'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['occurrence']))
            : [];

        if ($occurrenceUuids === []) {
            return ['error', __('No occurrences were selected.', 'bizupkeep-bookkeeping')];
        }

        foreach ($occurrenceUuids as $occurrenceUuid) {
            try {
                $this->recurring->confirmOccurrence($company->getUuid(), $occurrenceUuid, $userId);
            } catch (BookkeepingException) {
                continue;
            }
        }

        return ['success', __('Recurring transaction(s) confirmed and posted.', 'bizupkeep-bookkeeping')];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleRecurringSkip(int $userId, Company $company): ?array
    {
        if (! $this->isSubmittedAction('recurring_skip')) {
            return null;
        }

        if (! wp_verify_nonce($this->postParam(self::RECURRING_SKIP_NONCE_FIELD), self::RECURRING_SKIP_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        try {
            $this->recurring->skipOccurrence($company->getUuid(), $this->postParam('occurrence'));

            return ['success', __('Recurring transaction skipped.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleRecurringToggle(int $userId, Company $company): ?array
    {
        if (! $this->isSubmittedAction('recurring_toggle')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::RECURRING_TOGGLE_NONCE_FIELD),
            self::RECURRING_TOGGLE_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        $templateUuid = $this->postParam('template');
        $makeActive = $this->postParam('active') === '1';

        try {
            if ($makeActive) {
                $this->recurring->resumeTemplate($company->getUuid(), $templateUuid);
            } else {
                $this->recurring->pauseTemplate($company->getUuid(), $templateUuid);
            }

            return ['success', __('Recurring template updated.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleRecurringDelete(int $userId, Company $company): ?array
    {
        if (! $this->isSubmittedAction('recurring_delete')) {
            return null;
        }

        $nonceValid = wp_verify_nonce(
            $this->postParam(self::RECURRING_DELETE_NONCE_FIELD),
            self::RECURRING_DELETE_NONCE_ACTION
        );

        if (! $nonceValid) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-bookkeeping')];
        }

        try {
            $this->recurring->deleteTemplate($company->getUuid(), $this->postParam('template'));

            return ['success', __('Recurring template updated.', 'bizupkeep-bookkeeping')];
        } catch (BookkeepingException $exception) {
            return ['error', $exception->getMessage()];
        }
    }

    private function renderAccountsTab(Company $company): void
    {
        $accountsList = $this->accounts->listAccounts($company->getUuid());

        echo '<h2>' . esc_html__('Chart of Accounts', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Code', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Name', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Type', 'bizupkeep-bookkeeping') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($accountsList as $account) {
            echo '<tr><td>' . esc_html($account->code) . '</td>'
                . '<td>' . esc_html($account->name) . '</td>'
                . '<td>' . esc_html($account->type->label()) . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function renderStatementsTab(Company $company): void
    {
        $range = $this->parseRange();
        $asOf = $this->parseAsOf();

        echo '<form method="get" style="margin:1em 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '" />';
        echo '<input type="hidden" name="tab" value="statements" />';
        echo '<label>' . esc_html__('From', 'bizupkeep-bookkeeping') . ' <input type="date" name="from" value="'
            . esc_attr($range->from?->format('Y-m-d') ?? '') . '" /></label> ';
        echo '<label>' . esc_html__('To', 'bizupkeep-bookkeeping') . ' <input type="date" name="to" value="'
            . esc_attr($range->to->format('Y-m-d')) . '" /></label> ';
        echo '<label>' . esc_html__('Balance Sheet as of', 'bizupkeep-bookkeeping')
            . ' <input type="date" name="as_of" value="' . esc_attr($asOf->format('Y-m-d')) . '" /></label> ';
        submit_button(__('Update', 'bizupkeep-bookkeeping'), 'secondary', '', false);
        echo '</form>';

        try {
            $trialBalance = $this->statements->trialBalance($company->getUuid(), $range);
            $income = $this->statements->incomeStatement($company->getUuid(), $range);
            $balanceSheet = $this->statements->balanceSheet($company->getUuid(), $asOf);
        } catch (BookkeepingException) {
            echo '<p>'
                . esc_html__('Statements could not be generated for this range.', 'bizupkeep-bookkeeping')
                . '</p>';

            return;
        }

        echo '<h2>' . esc_html__('Trial Balance', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Account', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Debit', 'bizupkeep-bookkeeping') . '</th>'
            . '<th>' . esc_html__('Credit', 'bizupkeep-bookkeeping') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($trialBalance->rows as $row) {
            echo '<tr><td>' . esc_html($row->code . ' - ' . $row->name) . '</td>'
                . '<td>' . esc_html($row->debit->isZero() ? '' : $row->debit->format()) . '</td>'
                . '<td>' . esc_html($row->credit->isZero() ? '' : $row->credit->format()) . '</td></tr>';
        }

        echo '<tr><td><strong>' . esc_html__('Total', 'bizupkeep-bookkeeping') . '</strong></td>'
            . '<td><strong>' . esc_html($trialBalance->totalDebit->format()) . '</strong></td>'
            . '<td><strong>' . esc_html($trialBalance->totalCredit->format()) . '</strong></td></tr>';
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Income Statement', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<table class="widefat"><tbody>';

        foreach ($income->incomeRows as $row) {
            echo '<tr><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->net->format()) . '</td></tr>';
        }

        echo '<tr><td><strong>' . esc_html__('Total Income', 'bizupkeep-bookkeeping') . '</strong></td>'
            . '<td><strong>' . esc_html($income->totalIncome->format()) . '</strong></td></tr>';

        foreach ($income->expenseRows as $row) {
            echo '<tr><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->net->format()) . '</td></tr>';
        }

        echo '<tr><td><strong>' . esc_html__('Total Expenses', 'bizupkeep-bookkeeping') . '</strong></td>'
            . '<td><strong>' . esc_html($income->totalExpenses->format()) . '</strong></td></tr>';
        echo '<tr><td><strong>' . esc_html__('Net Income', 'bizupkeep-bookkeeping') . '</strong></td>'
            . '<td><strong>' . esc_html($income->netIncome->format()) . '</strong></td></tr>';
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Balance Sheet', 'bizupkeep-bookkeeping') . '</h2>';
        echo '<table class="widefat"><tbody>';
        echo '<tr><td colspan="2"><strong>' . esc_html__('Assets', 'bizupkeep-bookkeeping') . '</strong></td></tr>';

        foreach ($balanceSheet->assetRows as $row) {
            echo '<tr><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->net->format()) . '</td></tr>';
        }

        echo '<tr><td><strong>' . esc_html__('Total Assets', 'bizupkeep-bookkeeping') . '</strong></td>'
            . '<td><strong>' . esc_html($balanceSheet->totalAssets->format()) . '</strong></td></tr>';
        echo '<tr><td colspan="2"><strong>' . esc_html__('Liabilities', 'bizupkeep-bookkeeping')
            . '</strong></td></tr>';

        foreach ($balanceSheet->liabilityRows as $row) {
            echo '<tr><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->net->format()) . '</td></tr>';
        }

        echo '<tr><td><strong>' . esc_html__('Total Liabilities', 'bizupkeep-bookkeeping') . '</strong></td>'
            . '<td><strong>' . esc_html($balanceSheet->totalLiabilities->format()) . '</strong></td></tr>';
        echo '<tr><td colspan="2"><strong>' . esc_html__('Equity', 'bizupkeep-bookkeeping') . '</strong></td></tr>';

        foreach ($balanceSheet->equityRows as $row) {
            echo '<tr><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->net->format()) . '</td></tr>';
        }

        echo '<tr><td>' . esc_html__('Retained Earnings (prior years)', 'bizupkeep-bookkeeping') . '</td>'
            . '<td>' . esc_html($balanceSheet->priorYearsEarnings->format()) . '</td></tr>';
        echo '<tr><td>' . esc_html__('Current Year Earnings', 'bizupkeep-bookkeeping') . '</td>'
            . '<td>' . esc_html($balanceSheet->currentYearEarnings->format()) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Total Equity', 'bizupkeep-bookkeeping') . '</strong></td>'
            . '<td><strong>' . esc_html($balanceSheet->totalEquity->format()) . '</strong></td></tr>';
        echo '</tbody></table>';

        $vatRegistered = $this->companySettings->findByCompanyUuid($company->getUuid())?->isVatRegistered ?? false;

        if ($vatRegistered) {
            $vatSummary = $this->statements->vatSummary($company->getUuid(), $range);

            echo '<h2>' . esc_html__('VAT Summary', 'bizupkeep-bookkeeping') . '</h2>';
            echo '<table class="widefat"><tbody>';
            echo '<tr><td>' . esc_html__('Output VAT (on sales)', 'bizupkeep-bookkeeping') . '</td>'
                . '<td>' . esc_html($vatSummary->outputVat->format()) . '</td></tr>';
            echo '<tr><td>' . esc_html__('Input VAT (on purchases)', 'bizupkeep-bookkeeping') . '</td>'
                . '<td>' . esc_html($vatSummary->inputVat->format()) . '</td></tr>';
            $netLabel = $vatSummary->netVatPayable->isNegative()
                ? __('Net VAT Refundable', 'bizupkeep-bookkeeping')
                : __('Net VAT Payable', 'bizupkeep-bookkeeping');
            echo '<tr><td><strong>' . esc_html($netLabel) . '</strong></td>'
                . '<td><strong>' . esc_html($vatSummary->netVatPayable->format()) . '</strong></td></tr>';
            echo '</tbody></table>';
        }
    }

    private function renderExportTab(Company $company): void
    {
        $range = $this->parseRange();

        echo '<h2>' . esc_html__('Export', 'bizupkeep-bookkeeping') . '</h2>';

        echo '<form method="get" style="margin-bottom:1em;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '" />';
        echo '<input type="hidden" name="tab" value="export" />';
        echo '<label>' . esc_html__('From', 'bizupkeep-bookkeeping') . ' <input type="date" name="from" value="'
            . esc_attr($range->from?->format('Y-m-d') ?? '') . '" /></label> ';
        echo '<label>' . esc_html__('To', 'bizupkeep-bookkeeping') . ' <input type="date" name="to" value="'
            . esc_attr($range->to->format('Y-m-d')) . '" /></label> ';
        submit_button(__('Update Range', 'bizupkeep-bookkeeping'), 'secondary', '', false);
        echo '</form>';

        $platforms = [
            'quickbooks' => __('QuickBooks Online', 'bizupkeep-bookkeeping'),
            'xero' => __('Xero', 'bizupkeep-bookkeeping'),
            'sage' => __('Sage', 'bizupkeep-bookkeeping'),
        ];

        foreach ($platforms as $key => $label) {
            $url = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => self::EXPORT_ACTION,
                        'platform' => $key,
                        'from' => $range->from?->format('Y-m-d') ?? '',
                        'to' => $range->to->format('Y-m-d'),
                    ],
                    admin_url('admin-post.php')
                ),
                self::EXPORT_NONCE_ACTION,
                self::EXPORT_NONCE_FIELD
            );

            echo '<p><a href="' . esc_url($url) . '" class="button">'
                . esc_html(sprintf(
                    /* translators: %s: accounting platform name. */
                    __('Download %s CSV', 'bizupkeep-bookkeeping'),
                    $label
                )) . '</a></p>';
        }
    }

    private function currentTab(): string
    {
        $tab = $this->getParam('tab');

        return in_array($tab, self::TABS, true) ? $tab : 'capture';
    }

    /**
     * @param array<string,string> $args
     */
    private function pageUrl(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php'));
    }

    private function parseRange(): DateRange
    {
        $fromRaw = $this->getParam('from');
        $toRaw = $this->getParam('to');

        try {
            $to = $toRaw !== '' ? new DateTimeImmutable($toRaw) : new DateTimeImmutable();
        } catch (\Exception) {
            $to = new DateTimeImmutable();
        }

        if ($fromRaw === '') {
            return DateRange::monthToDate($to);
        }

        try {
            return DateRange::between(new DateTimeImmutable($fromRaw), $to);
        } catch (\Exception) {
            return DateRange::monthToDate($to);
        }
    }

    private function parseAsOf(): DateTimeImmutable
    {
        $raw = $this->getParam('as_of');

        if ($raw === '') {
            return new DateTimeImmutable();
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (\Exception) {
            return new DateTimeImmutable();
        }
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

    /**
     * Generic sanitized-GET-param reader. This page's GET-driven reads
     * are all navigation (tab/date-range selection), never mutation.
     */
    private function getParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation, no mutation.
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    /**
     * Generic sanitized-POST-param reader. Every caller using this for
     * a mutating action verifies its own nonce first, before reading
     * any other field via this method.
     */
    private function postParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the caller before this is used.
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function requestMethod(): string
    {
        return isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    }

    private function isSubmittedAction(string $action): bool
    {
        return $this->requestMethod() === 'POST'
            && $this->postParam('bizupkeep_bookkeeping_internal_action') === $action;
    }
}
