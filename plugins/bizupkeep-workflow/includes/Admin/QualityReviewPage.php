<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Documents\Exceptions\DocumentNotFoundException;
use BizHub\Documents\Services\DocumentService;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\DTO\WorkflowSummary;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;
use BizHub\Workflow\Policies\Capabilities;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationService;

/**
 * Staff-facing "Quality Review" admin screen: lists every Company
 * Registration workflow currently sitting in the QualityReview status
 * and lets a reviewer Approve or Reject it, per BH-WORKFLOW-SPEC-001's
 * Processing -> QualityReview -> Completed/Rejected lifecycle.
 *
 * Thin by the same design as CompanyRegistrationController: every
 * mutation is delegated to CompanyRegistrationService, which is the
 * only path that ever touches the workflow engine for this workflow
 * type. This page only adds the read-side aggregation (joining a
 * workflow instance to its Company, Client and Documents) needed to
 * actually review an application.
 *
 * @package BizHub\Workflow\Admin
 */
final class QualityReviewPage
{
    public const SLUG = 'bizhub-quality-review';

    private const NONCE_ACTION = 'bizupkeep_workflow_quality_review';

    private const NONCE_FIELD = 'bizupkeep_workflow_quality_review_nonce';

    /**
     * Upper bound on how many Company Registration workflows are
     * scanned to build the review queue. Generous for the business
     * volume this runs at; revisit with a status-filtered repository
     * query if that ever stops being true.
     */
    private const SCAN_LIMIT = 200;

    public function __construct(
        private readonly CompanyRegistrationService $registrations,
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly CompanyServiceInterface $companies,
        private readonly ClientRepositoryInterface $clients,
        private readonly DocumentService $documents,
        private readonly AuthorizationServiceInterface $authorization,
    ) {
    }

    /**
     * Render the page. Registered as the admin_menu callback for
     * self::SLUG.
     */
    public function render(): void
    {
        $userId = get_current_user_id();

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_VIEW)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-workflow'));
        }

        $workflowUuid = $this->param('workflow');
        $downloadUuid = $this->param('download');

        if ($workflowUuid !== '' && $downloadUuid !== '') {
            $this->streamDocument($userId, $workflowUuid, $downloadUuid);

            return;
        }

        $notice = $this->handleSubmission($userId);

        echo '<div class="wrap"><h1>' . esc_html__('Quality Review', 'bizupkeep-workflow') . '</h1>';

        if ($notice !== null) {
            [$type, $message] = $notice;
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>'
                . esc_html($message) . '</p></div>';
        }

        if ($workflowUuid !== '') {
            $this->renderDetail($workflowUuid);
        } else {
            $this->renderQueue();
        }

        echo '</div>';
    }

    /**
     * Handle an Approve/Reject POST submission, returning a
     * [notice-type, message] pair to display, or null if nothing was
     * submitted.
     *
     * @return array{0:string,1:string}|null
     */
    private function handleSubmission(int $userId): ?array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! isset($_POST['bizupkeep_action'])) {
            return null;
        }

        $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';

        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_TRANSITION)) {
            return ['error', __('You are not permitted to review applications.', 'bizupkeep-workflow')];
        }

        $workflowUuid = isset($_POST['workflow']) ? sanitize_text_field(wp_unslash($_POST['workflow'])) : '';
        $action = sanitize_text_field(wp_unslash($_POST['bizupkeep_action']));
        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        if ($action === CompanyRegistrationDefinition::ACTION_REJECT && trim($reason) === '') {
            return ['error', __('A reason is required to reject an application.', 'bizupkeep-workflow')];
        }

        $context = $action === CompanyRegistrationDefinition::ACTION_APPROVE
            ? ['reviewed_by' => $this->currentUserLabel()]
            : [];

        try {
            $this->registrations->performAction($workflowUuid, $action, $userId, $reason, $context);

            return ['success', $action === CompanyRegistrationDefinition::ACTION_APPROVE
                ? __('Application approved.', 'bizupkeep-workflow')
                : __('Application rejected.', 'bizupkeep-workflow')];
        } catch (ValidationException|PreconditionFailedException|InvalidTransitionException $exception) {
            return ['error', $exception->getMessage()];
        } catch (WorkflowNotFoundException $exception) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }
    }

    /**
     * Render the queue of applications awaiting quality review.
     */
    private function renderQueue(): void
    {
        $pending = $this->pendingReviews();

        if ($pending === []) {
            echo '<p>' . esc_html__('No applications are currently awaiting quality review.', 'bizupkeep-workflow') . '</p>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Company', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Registration No.', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('Client', 'bizupkeep-workflow') . '</th>'
            . '<th>' . esc_html__('In Review Since', 'bizupkeep-workflow') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        foreach ($pending as $summary) {
            $company = $this->companyFor($summary);
            $companyName = $company?->getCompanyName() ?? __('(company record missing)', 'bizupkeep-workflow');
            $regNumber = $company?->getRegistrationNumber() ?? '—';
            $client = $company !== null ? $this->clientLabelFor($company) : '—';
            $since = ($summary->updatedAt ?? $summary->createdAt)->format('Y-m-d H:i');
            $reviewUrl = add_query_arg(
                ['page' => self::SLUG, 'workflow' => $summary->uuid],
                admin_url('admin.php')
            );

            echo '<tr>'
                . '<td>' . esc_html($companyName) . '</td>'
                . '<td>' . esc_html($regNumber) . '</td>'
                . '<td>' . esc_html($client) . '</td>'
                . '<td>' . esc_html($since) . '</td>'
                . '<td><a class="button button-primary" href="' . esc_url($reviewUrl) . '">'
                . esc_html__('Review', 'bizupkeep-workflow') . '</a></td>'
                . '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render a single application's review detail: company info,
     * uploaded documents, and the Approve/Reject form.
     */
    private function renderDetail(string $workflowUuid): void
    {
        try {
            $workflow = $this->registrations->find($workflowUuid);
        } catch (WorkflowNotFoundException) {
            echo '<p>' . esc_html__('That application could not be found.', 'bizupkeep-workflow') . '</p>';

            return;
        }

        $backUrl = add_query_arg(['page' => self::SLUG], admin_url('admin.php'));
        echo '<p><a href="' . esc_url($backUrl) . '">&larr; '
            . esc_html__('Back to queue', 'bizupkeep-workflow') . '</a></p>';

        if ($workflow->getStatus() !== WorkflowStatus::QualityReview) {
            echo '<div class="notice notice-warning"><p>' . esc_html(sprintf(
                /* translators: %s: workflow status label */
                __('This application is no longer awaiting quality review (current status: %s).', 'bizupkeep-workflow'),
                $workflow->getStatus()->label()
            )) . '</p></div>';

            return;
        }

        $company = null;

        try {
            $company = $this->companies->getCompany($workflow->getSubjectUuid());
        } catch (CompanyNotFoundException) {
            // Fall through - render what we can without company details.
        }

        echo '<h2>' . esc_html($company?->getCompanyName() ?? __('(company record missing)', 'bizupkeep-workflow')) . '</h2>';

        if ($company !== null) {
            $this->renderCompanyDetails($company);
            $this->renderDocuments($workflow, $company);
        }

        $this->renderReviewForm($workflow);
    }

    /**
     * @param Company $company
     */
    private function renderCompanyDetails(Company $company): void
    {
        echo '<table class="form-table"><tbody>';
        $this->row(__('Registration Number', 'bizupkeep-workflow'), $company->getRegistrationNumber());
        $this->row(__('Company Type', 'bizupkeep-workflow'), $company->getCompanyType());
        $this->row(__('Client', 'bizupkeep-workflow'), $this->clientLabelFor($company));
        $this->row(__('Registered Address', 'bizupkeep-workflow'), $company->getRegisteredAddress()->getFormattedAddress());
        echo '</tbody></table>';
    }

    private function row(string $label, string $value): void
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }

    private function renderDocuments(WorkflowInstance $workflow, Company $company): void
    {
        $documents = $this->documents->getDocumentsForOwner('company', $company->getUuid());

        echo '<h3>' . esc_html__('Submitted Documents', 'bizupkeep-workflow') . '</h3>';

        if ($documents === []) {
            echo '<p>' . esc_html__('No documents have been uploaded for this company.', 'bizupkeep-workflow') . '</p>';

            return;
        }

        echo '<ul>';

        foreach ($documents as $document) {
            $downloadUrl = add_query_arg(
                ['page' => self::SLUG, 'workflow' => $workflow->getUuid(), 'download' => $document->getUuid()],
                admin_url('admin.php')
            );

            echo '<li>' . esc_html($document->getCategory()->label()) . ' &mdash; '
                . '<a href="' . esc_url($downloadUrl) . '">' . esc_html($document->getName()) . '</a></li>';
        }

        echo '</ul>';
    }

    private function renderReviewForm(WorkflowInstance $workflow): void
    {
        echo '<h3>' . esc_html__('Decision', 'bizupkeep-workflow') . '</h3>';
        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';

        echo '<p><label for="bizupkeep-reason">' . esc_html__('Notes (required to reject)', 'bizupkeep-workflow')
            . '</label><br />'
            . '<textarea id="bizupkeep-reason" name="reason" rows="4" class="large-text"></textarea></p>';

        echo '<p>'
            . '<button type="submit" name="bizupkeep_action" value="'
            . esc_attr(CompanyRegistrationDefinition::ACTION_APPROVE) . '" class="button button-primary">'
            . esc_html__('Approve', 'bizupkeep-workflow') . '</button> '
            . '<button type="submit" name="bizupkeep_action" value="'
            . esc_attr(CompanyRegistrationDefinition::ACTION_REJECT) . '" class="button">'
            . esc_html__('Reject', 'bizupkeep-workflow') . '</button>'
            . '</p>';

        echo '</form>';
    }

    /**
     * Stream a submitted document's current version to the browser,
     * re-verifying it actually belongs to the company under review
     * before serving it.
     */
    private function streamDocument(int $userId, string $workflowUuid, string $documentUuid): void
    {
        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_VIEW)) {
            wp_die(esc_html__('You are not permitted to access this document.', 'bizupkeep-workflow'));
        }

        try {
            $workflow = $this->registrations->find($workflowUuid);
            $company = $this->companies->getCompany($workflow->getSubjectUuid());
            $document = $this->documents->getDocument($documentUuid);
        } catch (WorkflowNotFoundException|CompanyNotFoundException|DocumentNotFoundException) {
            wp_die(esc_html__('That document could not be found.', 'bizupkeep-workflow'));
        }

        if ($document->getOwnerType() !== 'company' || $document->getOwnerUuid() !== $company->getUuid()) {
            wp_die(esc_html__('That document does not belong to this application.', 'bizupkeep-workflow'));
        }

        $version = $document->getCurrentVersion();

        if ($version === null || ! is_file($version->filePath)) {
            wp_die(esc_html__('That document file is no longer available.', 'bizupkeep-workflow'));
        }

        nocache_headers();
        header('Content-Type: ' . $version->mimeType);
        header('Content-Disposition: attachment; filename="' . basename($version->filePath) . '"');
        header('Content-Length: ' . (string) $version->fileSize);

        readfile($version->filePath);

        exit;
    }

    /**
     * @return array<int,WorkflowSummary>
     */
    private function pendingReviews(): array
    {
        $summaries = $this->workflows->summaries(CompanyRegistrationDefinition::TYPE, self::SCAN_LIMIT);

        $pending = array_values(array_filter(
            $summaries,
            static fn (WorkflowSummary $summary): bool => $summary->status === WorkflowStatus::QualityReview
        ));

        usort(
            $pending,
            static fn (WorkflowSummary $a, WorkflowSummary $b): int =>
                ($a->updatedAt ?? $a->createdAt) <=> ($b->updatedAt ?? $b->createdAt)
        );

        return $pending;
    }

    private function companyFor(WorkflowSummary $summary): ?Company
    {
        try {
            return $this->companies->getCompany($summary->subjectUuid);
        } catch (CompanyNotFoundException) {
            return null;
        }
    }

    private function clientLabelFor(Company $company): string
    {
        $client = $this->clients->find($company->getClientId());

        if ($client === null) {
            return '—';
        }

        $wpUser = get_userdata($client->getWpUserId());

        return $wpUser instanceof \WP_User ? $wpUser->display_name . ' <' . $wpUser->user_email . '>' : '—';
    }

    private function param(string $key): string
    {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    /**
     * A human-readable label for the currently logged-in reviewer,
     * recorded as the workflow's "reviewed_by" context. Falls back to
     * the login name for accounts without a display name set.
     */
    private function currentUserLabel(): string
    {
        $user = wp_get_current_user();

        return trim($user->display_name) !== '' ? $user->display_name : $user->user_login;
    }
}
