<?php

declare(strict_types=1);

namespace BizHub\Stub\Admin;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Stub\Contracts\StubApiClientInterface;
use BizHub\Stub\Contracts\StubAuthTokenServiceInterface;
use BizHub\Stub\Contracts\StubBusinessProvisionerInterface;
use BizHub\Stub\Contracts\StubBusinessRepositoryInterface;
use BizHub\Stub\Contracts\StubMigrationServiceInterface;
use BizHub\Stub\Exceptions\StubException;
use BizHub\Stub\Policies\Capabilities;

/**
 * Staff-facing "view a client's books" screen: enter a Company UUID,
 * provision (or reuse) its Stub business, and read its
 * summary/insights/expenses/income straight from Stub via the
 * realtime/* endpoints - no embedded widget, no client-side token,
 * synchronous server-side reads only.
 *
 * There is deliberately no "pick any company" dropdown: neither
 * CompanyServiceInterface nor CompanyRepositoryInterface expose a
 * list-every-company operation anywhere in bizhub (both are scoped to
 * "companies for one client"), so this mirrors bizupkeep-payments'
 * PaymentsDashboardPage in spirit (a plain lookup/table, no invented
 * new bizhub capability) rather than building one just for this page.
 * The table of already-provisioned businesses below the lookup form
 * gives staff a starting point once at least one company has been
 * viewed/migrated once.
 *
 * @package BizHub\Stub\Admin
 */
final class StubDashboardPage
{
    public const SLUG = 'bizupkeep-stub-dashboard';

    // No nonce constants for the lookup form itself: it's a plain GET
    // (?company_uuid=...), read-only, no state change - nothing to
    // protect with a nonce.

    private const MIGRATE_NONCE_ACTION = 'bizupkeep_stub_migrate';

    private const MIGRATE_NONCE_FIELD = 'bizupkeep_stub_migrate_nonce';

    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly CompanyServiceInterface $companies,
        private readonly ClientRepositoryInterface $clients,
        private readonly StubBusinessRepositoryInterface $businesses,
        private readonly StubBusinessProvisionerInterface $provisioner,
        private readonly StubAuthTokenServiceInterface $tokens,
        private readonly StubApiClientInterface $api,
        private readonly StubMigrationServiceInterface $migration
    ) {
    }

    public function render(): void
    {
        if (! $this->authorization->can(get_current_user_id(), Capabilities::MANAGE_STUB)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-stub'));
        }

        $notice = $this->handleMigrate();

        echo '<div class="wrap"><h1>' . esc_html__('Stub Bookkeeping', 'bizupkeep-stub') . '</h1>';

        $this->renderNotice($notice);
        $this->renderLookupForm();

        $companyUuid = $this->lookupCompanyUuid();

        if ($companyUuid !== null) {
            $this->renderCompanyBooks($companyUuid);
        }

        $this->renderBusinessesTable();

        echo '</div>';
    }

    private function lookupCompanyUuid(): ?string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup, no state change.
        $uuid = isset($_GET['company_uuid']) ? sanitize_text_field(wp_unslash($_GET['company_uuid'])) : '';

        return $uuid !== '' ? $uuid : null;
    }

    private function renderLookupForm(): void
    {
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '" />';
        echo '<p><label for="company_uuid">' . esc_html__('Company UUID', 'bizupkeep-stub') . '</label> ';
        echo '<input type="text" id="company_uuid" name="company_uuid" class="regular-text" value="'
            . esc_attr($this->lookupCompanyUuid() ?? '') . '" /> ';
        submit_button(__('View Books', 'bizupkeep-stub'), 'primary', 'submit', false);
        echo '</p></form>';
    }

    private function renderCompanyBooks(string $companyUuid): void
    {
        try {
            $company = $this->companies->getCompany($companyUuid);
        } catch (CompanyNotFoundException $e) {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('No company with that UUID.', 'bizupkeep-stub')
                . '</p></div>';

            return;
        }

        try {
            $business = $this->businesses->findByCompanyUuid($companyUuid);

            if ($business === null) {
                $client = $this->clients->find($company->getClientId());

                if ($client === null) {
                    throw new StubException('This company has no owning client.');
                }

                $this->provisioner->findOrCreate($client, $company);
                $business = $this->businesses->findByCompanyUuid($companyUuid);
            }

            if ($business === null) {
                throw new StubException('Could not provision a Stub business for this company.');
            }

            $token = $this->tokens->getToken($business->stubBusinessUid);
            $summary = $this->api->realtimeSummary($token, $business->stubBusinessUid);
            $insights = $this->api->realtimeInsights($token, $business->stubBusinessUid);
        } catch (StubException $e) {
            echo '<div class="notice notice-error"><p>' . esc_html(sprintf(
                /* translators: %s: error detail from Stub. */
                __('Could not read this company\'s Stub books: %s', 'bizupkeep-stub'),
                $e->getMessage()
            )) . '</p></div>';

            return;
        }

        echo '<h2>' . esc_html($company->getCompanyName()) . '</h2>';

        if (! $business->isMigrated()) {
            echo '<form method="post">';
            wp_nonce_field(self::MIGRATE_NONCE_ACTION, self::MIGRATE_NONCE_FIELD);
            echo '<input type="hidden" name="bizupkeep_stub_action" value="migrate" />';
            echo '<input type="hidden" name="company_uuid" value="' . esc_attr($companyUuid) . '" />';
            submit_button(__('Migrate Historical Data to Stub', 'bizupkeep-stub'), 'secondary');
            echo '</form>';

            if ($business->migrationFailedReason !== null) {
                echo '<p class="notice notice-error" style="padding:8px;">' . esc_html(sprintf(
                    /* translators: %s: error detail from a previous failed migration attempt. */
                    __('Last migration attempt failed: %s', 'bizupkeep-stub'),
                    $business->migrationFailedReason
                )) . '</p>';
            }
        } else {
            echo '<p><em>' . esc_html__('Historical data already migrated.', 'bizupkeep-stub') . '</em></p>';
        }

        echo '<h3>' . esc_html__('Summary', 'bizupkeep-stub') . '</h3><pre>'
            . esc_html((string) wp_json_encode($summary, JSON_PRETTY_PRINT)) . '</pre>';
        echo '<h3>' . esc_html__('Insights', 'bizupkeep-stub') . '</h3><pre>'
            . esc_html((string) wp_json_encode($insights, JSON_PRETTY_PRINT)) . '</pre>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleMigrate(): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_stub_action') !== 'migrate') {
            return null;
        }

        check_admin_referer(self::MIGRATE_NONCE_ACTION, self::MIGRATE_NONCE_FIELD);

        $companyUuid = $this->postParam('company_uuid');

        try {
            $this->migration->migrateCompany($companyUuid);

            return ['success', __('Migration complete.', 'bizupkeep-stub')];
        } catch (StubException $e) {
            return ['error', sprintf(
                /* translators: %s: error detail. */
                __('Migration failed: %s', 'bizupkeep-stub'),
                $e->getMessage()
            )];
        }
    }

    private function renderBusinessesTable(): void
    {
        $businesses = $this->businesses->findAll();

        echo '<h2>' . esc_html__('Provisioned Stub Businesses', 'bizupkeep-stub') . '</h2>';
        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Company UUID', 'bizupkeep-stub') . '</th>'
            . '<th>' . esc_html__('Stub Business UID', 'bizupkeep-stub') . '</th>'
            . '<th>' . esc_html__('Created', 'bizupkeep-stub') . '</th>'
            . '<th>' . esc_html__('Migrated', 'bizupkeep-stub') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        foreach ($businesses as $business) {
            $viewUrl = add_query_arg(
                ['page' => self::SLUG, 'company_uuid' => $business->companyUuid],
                admin_url('admin.php')
            );

            echo '<tr>';
            echo '<td><code>' . esc_html($business->companyUuid) . '</code></td>';
            echo '<td><code>' . esc_html($business->stubBusinessUid) . '</code></td>';
            echo '<td>' . esc_html($business->createdAt->format('Y-m-d H:i')) . '</td>';
            echo '<td>' . esc_html(
                $business->isMigrated()
                    ? $business->migratedAt->format('Y-m-d H:i')
                    : __('Not yet', 'bizupkeep-stub')
            ) . '</td>';
            echo '<td><a href="' . esc_url($viewUrl) . '">' . esc_html__('View', 'bizupkeep-stub') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
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
