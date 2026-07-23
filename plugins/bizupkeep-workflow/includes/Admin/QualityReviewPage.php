<?php

declare(strict_types=1);

namespace BizHub\Workflow\Admin;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\DTO\AddressData;
use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Companies\Exceptions\InvalidCompanyException;
use BizHub\Documents\Entities\DocumentCategory;
use BizHub\Documents\Exceptions\DocumentNotFoundException;
use BizHub\Documents\Services\DocumentService;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\Contracts\WorkflowTypeServiceInterface;
use BizHub\Workflow\DTO\WorkflowSummary;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;
use BizHub\Workflow\Policies\Capabilities;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnService;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentService;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationService;
use RuntimeException;

/**
 * Staff-facing "Quality Review" admin screen: lists every workflow
 * instance - across all three workflow types (Company Registration,
 * Company Amendment, Annual Return) - currently sitting in the
 * QualityReview status, and lets a reviewer Approve or Reject it, per
 * BH-WORKFLOW-SPEC-001's Processing -> QualityReview ->
 * Completed/Rejected lifecycle.
 *
 * Thin by the same design as each type's own Controller: every
 * mutation is delegated to that workflow's Service class (the only
 * path that ever touches the workflow engine for its type), resolved
 * generically via WorkflowTypeServiceInterface + serviceFor() rather
 * than this page hardcoding one type. This page only adds the
 * read-side aggregation (joining a workflow instance to its Company,
 * Client and Documents) needed to actually review an application.
 *
 * @package BizHub\Workflow\Admin
 */
final class QualityReviewPage
{
    public const SLUG = 'bizhub-quality-review';

    private const NONCE_ACTION = 'bizupkeep_workflow_quality_review';

    private const NONCE_FIELD = 'bizupkeep_workflow_quality_review_nonce';

    private const UPLOAD_NONCE_ACTION = 'bizupkeep_workflow_staff_upload';

    private const UPLOAD_NONCE_FIELD = 'bizupkeep_workflow_staff_upload_nonce';

    private const QUOTE_NONCE_ACTION = 'bizupkeep_workflow_send_quote';

    private const QUOTE_NONCE_FIELD = 'bizupkeep_workflow_send_quote_nonce';

    private const FORCE_STATUS_NONCE_ACTION = 'bizupkeep_workflow_force_status';

    private const FORCE_STATUS_NONCE_FIELD = 'bizupkeep_workflow_force_status_nonce';

    private const DELETE_VERSION_NONCE_ACTION = 'bizupkeep_workflow_delete_version';

    private const DELETE_VERSION_NONCE_FIELD = 'bizupkeep_workflow_delete_version_nonce';

    private const BULK_NONCE_ACTION = 'bizupkeep_workflow_bulk_review';

    private const BULK_NONCE_FIELD = 'bizupkeep_workflow_bulk_review_nonce';

    private const REGISTRATION_NUMBER_NONCE_ACTION = 'bizupkeep_workflow_update_registration_number';

    private const REGISTRATION_NUMBER_NONCE_FIELD = 'bizupkeep_workflow_update_registration_number_nonce';

    private const ROLLBACK_NONCE_ACTION = 'bizupkeep_workflow_rollback';

    private const ROLLBACK_NONCE_FIELD = 'bizupkeep_workflow_rollback_nonce';

    /**
     * Matches the client-facing upload form's own allowed types
     * (functions.php's bizupkeep_child_validate_uploaded_file()) - a
     * staff upload is just as likely to be a scanned certificate or
     * photo as anything a client submits.
     *
     * @var array<int,string>
     */
    private const ALLOWED_UPLOAD_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * Larger than the client-facing form's 5MB cap - a staff-uploaded
     * CIPC certificate or multi-page filing scanned as one PDF is
     * plausibly bigger than a single ID photo or POA.
     */
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    private const ACTION_APPROVE = 'approve';

    private const ACTION_REJECT = 'reject';

    private const ACTION_REJECT_NAME = 'reject_name';

    /**
     * The workflow types this screen reviews, in display order.
     *
     * @var array<int,string>
     */
    private const REVIEWED_TYPES = [
        CompanyRegistrationDefinition::TYPE,
        CompanyAmendmentDefinition::TYPE,
        AnnualReturnDefinition::TYPE,
    ];

    /**
     * Annual Return has no Reject action in its state machine (see
     * AnnualReturnDefinition) - CIPC does not "reject" a compliant
     * filing the way it might a name change - so it's the one
     * reviewed type excluded here.
     *
     * @var array<int,string>
     */
    private const REJECTABLE_TYPES = [
        CompanyRegistrationDefinition::TYPE,
        CompanyAmendmentDefinition::TYPE,
    ];

    /**
     * Upper bound on how many workflow instances of a single type are
     * scanned per query to build the review queue. Generous for the
     * business volume this runs at.
     */
    private const SCAN_LIMIT = 200;

    public function __construct(
        private readonly CompanyRegistrationService $registrations,
        private readonly CompanyAmendmentService $amendments,
        private readonly AnnualReturnService $annualReturns,
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

        $notice = $this->handleDocumentUpload($userId)
            ?? $this->handleVersionDelete($userId)
            ?? $this->handleSendQuote($userId)
            ?? $this->handleForceStatus($userId)
            ?? $this->handleBulkAction($userId)
            ?? $this->handleBulkUpload($userId)
            ?? $this->handleUpdateRegistrationNumber($userId)
            ?? $this->handleRollback($userId)
            ?? $this->handleSubmission($userId);

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
        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';

        if ($requestMethod !== 'POST' || ! isset($_POST['bizupkeep_action'])) {
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

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        if ($action === self::ACTION_REJECT && ! in_array($workflow->getWorkflowType(), self::REJECTABLE_TYPES, true)) {
            return ['error', __('This application type cannot be rejected.', 'bizupkeep-workflow')];
        }

        $isRejectName = $action === self::ACTION_REJECT_NAME;

        if ($isRejectName && ! $this->canRejectName($workflow)) {
            return ['error', __('This application has no proposed name to reject.', 'bizupkeep-workflow')];
        }

        if (in_array($action, [self::ACTION_REJECT, self::ACTION_REJECT_NAME], true) && trim($reason) === '') {
            return ['error', __('A reason is required to reject an application.', 'bizupkeep-workflow')];
        }

        $context = $action === self::ACTION_APPROVE
            ? ['reviewed_by' => $this->currentUserLabel()]
            : [];

        try {
            $this->serviceFor($workflow->getWorkflowType())
                ->performAction($workflowUuid, $action, $userId, $reason, $context);

            $message = __('Application rejected.', 'bizupkeep-workflow');

            if ($action === self::ACTION_APPROVE) {
                $message = __('Application approved.', 'bizupkeep-workflow');
            } elseif ($isRejectName) {
                $message = __(
                    'Names rejected - the client has been asked to submit new options.',
                    'bizupkeep-workflow'
                );
            }

            return ['success', $message];
        } catch (ValidationException | PreconditionFailedException | InvalidTransitionException $exception) {
            return ['error', $exception->getMessage()];
        } catch (WorkflowNotFoundException $exception) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }
    }

    /**
     * Handle a staff document-upload POST submission (a separate form
     * from the Approve/Reject one, gated by its own nonce/marker field
     * so the two never collide), returning a [notice-type, message]
     * pair to display, or null if nothing was submitted. Lets staff
     * attach a document to the application's company - e.g. the final
     * CIPC certificate once a Registration/Amendment is Completed -
     * which then appears on the client's My Documents page
     * automatically, since that page lists every document for the
     * company regardless of category.
     *
     * @return array{0:string,1:string}|null
     */
    private function handleDocumentUpload(int $userId): ?array
    {
        if (! isset($_POST[self::UPLOAD_NONCE_FIELD])) {
            return null;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::UPLOAD_NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::UPLOAD_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_TRANSITION)) {
            return ['error', __('You are not permitted to upload documents.', 'bizupkeep-workflow')];
        }

        $workflowUuid = isset($_POST['workflow']) ? sanitize_text_field(wp_unslash($_POST['workflow'])) : '';
        $targetDocumentUuid = isset($_POST['target_document'])
            ? sanitize_text_field(wp_unslash($_POST['target_document']))
            : '';
        $categoryRaw = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        try {
            $company = $this->companies->getCompany($workflow->getSubjectUuid());
        } catch (CompanyNotFoundException) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        $file = $this->validateUploadedFile();

        if ($file === null) {
            return ['error', __(
                'That upload could not be processed - please check the file (PDF, JPG or PNG, max 10MB) and try again.',
                'bizupkeep-workflow'
            )];
        }

        /*
         * Replace flow (see renderReplaceVersionForm()): attach the
         * upload as a new version of an existing document instead of
         * creating a brand-new one. The category picker plays no part
         * here - the document already has one.
         */
        if ($targetDocumentUuid !== '') {
            try {
                $document = $this->documents->getDocument($targetDocumentUuid);
            } catch (DocumentNotFoundException) {
                return ['error', __('That document could not be found.', 'bizupkeep-workflow')];
            }

            if ($document->getOwnerType() !== 'company' || $document->getOwnerUuid() !== $company->getUuid()) {
                return ['error', __('That document does not belong to this application.', 'bizupkeep-workflow')];
            }

            try {
                $this->documents->addVersion($targetDocumentUuid, $file['tmp_name'], $file['name'], $userId);
            } catch (\Throwable $exception) {
                return ['error', __(
                    'That upload could not be processed - please check the file (PDF, JPG or PNG, max 10MB) and try again.',
                    'bizupkeep-workflow'
                )];
            }

            return ['success', __('New version uploaded.', 'bizupkeep-workflow')];
        }

        $category = null;

        foreach (DocumentCategory::cases() as $case) {
            if ($case->value === $categoryRaw) {
                $category = $case;

                break;
            }
        }

        if ($category === null) {
            return ['error', __('Please choose a document type.', 'bizupkeep-workflow')];
        }

        try {
            $this->documents->uploadDocument(
                'company',
                $company->getUuid(),
                $file['name'],
                $category,
                $file['tmp_name'],
                $file['name'],
                $userId
            );
        } catch (\Throwable $exception) {
            return ['error', __(
                'That upload could not be processed - please check the file (PDF, JPG or PNG, max 10MB) and try again.',
                'bizupkeep-workflow'
            )];
        }

        return ['success', __('Document uploaded.', 'bizupkeep-workflow')];
    }

    /**
     * Handle a single-version delete POST submission (see
     * renderDeleteVersionButton()) - lets staff remove one bad upload
     * without losing the document's other versions/history. Refuses
     * (via DocumentService::deleteVersion()) to remove a document's
     * only remaining version - use the existing whole-document delete
     * path for that instead.
     *
     * @return array{0:string,1:string}|null
     */
    private function handleVersionDelete(int $userId): ?array
    {
        if (! isset($_POST[self::DELETE_VERSION_NONCE_FIELD])) {
            return null;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::DELETE_VERSION_NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::DELETE_VERSION_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_TRANSITION)) {
            return ['error', __('You are not permitted to delete documents.', 'bizupkeep-workflow')];
        }

        $workflowUuid = isset($_POST['workflow']) ? sanitize_text_field(wp_unslash($_POST['workflow'])) : '';
        $documentUuid = isset($_POST['target_document'])
            ? sanitize_text_field(wp_unslash($_POST['target_document']))
            : '';
        $versionUuid = isset($_POST['version']) ? sanitize_text_field(wp_unslash($_POST['version'])) : '';

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        try {
            $company = $this->companies->getCompany($workflow->getSubjectUuid());
            $document = $this->documents->getDocument($documentUuid);
        } catch (CompanyNotFoundException | DocumentNotFoundException) {
            return ['error', __('That document could not be found.', 'bizupkeep-workflow')];
        }

        if ($document->getOwnerType() !== 'company' || $document->getOwnerUuid() !== $company->getUuid()) {
            return ['error', __('That document does not belong to this application.', 'bizupkeep-workflow')];
        }

        try {
            $this->documents->deleteVersion($documentUuid, $versionUuid);
        } catch (DocumentNotFoundException) {
            return ['error', __('That document could not be found.', 'bizupkeep-workflow')];
        } catch (\InvalidArgumentException $exception) {
            return ['error', $exception->getMessage()];
        }

        return ['success', __('Version deleted.', 'bizupkeep-workflow')];
    }

    /**
     * Read, validate, and return the uploaded file's PHP $_FILES entry,
     * or null if it's missing, failed, too large, or not an allowed
     * type. Mirrors the client-facing upload form's own validation
     * (functions.php's bizupkeep_child_validate_uploaded_file()) -
     * DocumentService/DocumentStorageService enforce neither size nor
     * mime type themselves.
     *
     * @return array{name:string,tmp_name:string}|null
     */
    private function validateUploadedFile(string $fieldName = 'document'): ?array
    {
        if (empty($_FILES[$fieldName]) || ! is_array($_FILES[$fieldName])) {
            return null;
        }

        $file = $_FILES[$fieldName];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';

        if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
            return null;
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            return null;
        }

        $name = is_string($file['name'] ?? null) ? $file['name'] : '';
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {
            return null;
        }

        return ['name' => $name, 'tmp_name' => $tmpName];
    }

    /**
     * Handle the staff "Send Quote" / "Revise Quote" POST submission
     * for an Annual Return application - the "staff to check annual
     * returns on CIPC site >> send quote to client" step from the
     * workflow spec, plus the ability to correct a wrong amount before
     * the client pays. Which action actually fires - request_payment
     * (Created -> AwaitingPayment) or revise_quote (a same-status
     * AwaitingPayment -> AwaitingPayment overwrite) - is derived here
     * from the workflow's OWN current status, not trusted from the
     * form, so a client can't be tricked into anything by a stale page
     * or a tampered request: the exact same form posts to both cases,
     * renderQuoteForm() just labels the button differently.
     *
     * @return array{0:string,1:string}|null
     */
    private function handleSendQuote(int $userId): ?array
    {
        if (! isset($_POST[self::QUOTE_NONCE_FIELD])) {
            return null;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::QUOTE_NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::QUOTE_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_TRANSITION)) {
            return ['error', __('You are not permitted to send a quote.', 'bizupkeep-workflow')];
        }

        $workflowUuid = isset($_POST['workflow']) ? sanitize_text_field(wp_unslash($_POST['workflow'])) : '';
        $amountRaw = isset($_POST['quote_amount']) ? sanitize_text_field(wp_unslash($_POST['quote_amount'])) : '';
        $notes = isset($_POST['quote_notes']) ? sanitize_textarea_field(wp_unslash($_POST['quote_notes'])) : '';

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || $workflow->getWorkflowType() !== AnnualReturnDefinition::TYPE) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        if (! is_numeric($amountRaw) || (float) $amountRaw <= 0.0) {
            return ['error', __('Please enter a quote amount greater than zero.', 'bizupkeep-workflow')];
        }

        $isRevision = $workflow->getStatus() === WorkflowStatus::AwaitingPayment;
        $action = $isRevision
            ? AnnualReturnDefinition::ACTION_REVISE_QUOTE
            : AnnualReturnDefinition::ACTION_REQUEST_PAYMENT;

        try {
            $this->annualReturns->performAction(
                $workflowUuid,
                $action,
                $userId,
                sprintf(
                    /* translators: 1: "Quote sent" or "Quote revised", 2: staff member's display name */
                    __('%1$s by %2$s.', 'bizupkeep-workflow'),
                    $isRevision ? __('Quote revised', 'bizupkeep-workflow') : __('Quote sent', 'bizupkeep-workflow'),
                    $this->currentUserLabel()
                ),
                ['quote_amount' => (float) $amountRaw, 'quote_notes' => $notes]
            );

            return ['success', $isRevision
                ? __('Quote revised.', 'bizupkeep-workflow')
                : __('Quote sent - the client can now pay.', 'bizupkeep-workflow')];
        } catch (ValidationException | PreconditionFailedException | InvalidTransitionException $exception) {
            return ['error', $exception->getMessage()];
        } catch (WorkflowNotFoundException $exception) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }
    }

    /**
     * Handle the "Override Status" POST submission (see
     * renderForceStatusForm()) - the staff "unstick a workflow" escape
     * hatch, gated by the stricter Capabilities::WORKFLOW_MANAGE rather
     * than WORKFLOW_TRANSITION since it bypasses every normal guard.
     *
     * @return array{0:string,1:string}|null
     */
    private function handleForceStatus(int $userId): ?array
    {
        if (! isset($_POST[self::FORCE_STATUS_NONCE_FIELD])) {
            return null;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::FORCE_STATUS_NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::FORCE_STATUS_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_MANAGE)) {
            return ['error', __('You are not permitted to override an application status.', 'bizupkeep-workflow')];
        }

        $workflowUuid = isset($_POST['workflow']) ? sanitize_text_field(wp_unslash($_POST['workflow'])) : '';
        $statusRaw = isset($_POST['force_status']) ? sanitize_text_field(wp_unslash($_POST['force_status'])) : '';
        $reason = isset($_POST['force_reason']) ? sanitize_textarea_field(wp_unslash($_POST['force_reason'])) : '';

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        $status = null;

        foreach (WorkflowStatus::cases() as $case) {
            if ($case->value === $statusRaw) {
                $status = $case;

                break;
            }
        }

        if ($status === null) {
            return ['error', __('Please choose a status.', 'bizupkeep-workflow')];
        }

        if (trim($reason) === '') {
            return ['error', __('A reason is required to override a status.', 'bizupkeep-workflow')];
        }

        try {
            $this->serviceFor($workflow->getWorkflowType())->forceStatus($workflowUuid, $status, $userId, $reason);

            return ['success', __('Status overridden.', 'bizupkeep-workflow')];
        } catch (InvalidTransitionException $exception) {
            return ['error', $exception->getMessage()];
        } catch (WorkflowNotFoundException $exception) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }
    }

    /**
     * Handle the "Undo Last Step" POST submission (see
     * renderRollbackButton()) - reverts a workflow to whatever status
     * it was in immediately before its most recent transition, per
     * WorkflowEngineInterface::rollback(). This existed at the engine
     * level since the workflow was first built, but no admin screen
     * ever called it - staff had no way to trigger it. Gated by the
     * same stricter Capabilities::WORKFLOW_MANAGE as Override Status,
     * since rollback() also bypasses the transition guard (unlike
     * Approve/Reject/etc., it does not re-validate business rules for
     * the status it lands on) - it is simply more constrained than
     * Override Status, landing on exactly the previous status rather
     * than any status chosen freely.
     *
     * @return array{0:string,1:string}|null
     */
    private function handleRollback(int $userId): ?array
    {
        if (! isset($_POST[self::ROLLBACK_NONCE_FIELD])) {
            return null;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::ROLLBACK_NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::ROLLBACK_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_MANAGE)) {
            return ['error', __('You are not permitted to roll back an application.', 'bizupkeep-workflow')];
        }

        $workflowUuid = isset($_POST['workflow']) ? sanitize_text_field(wp_unslash($_POST['workflow'])) : '';
        $reason = isset($_POST['rollback_reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['rollback_reason']))
            : '';

        if (trim($reason) === '') {
            return ['error', __('A reason is required to roll back an application.', 'bizupkeep-workflow')];
        }

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        try {
            $this->serviceFor($workflow->getWorkflowType())->rollback($workflowUuid, $userId, $reason);

            return ['success', __('Application rolled back to its previous status.', 'bizupkeep-workflow')];
        } catch (InvalidTransitionException $exception) {
            return ['error', $exception->getMessage()];
        } catch (WorkflowNotFoundException $exception) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }
    }

    /**
     * Handle a bulk Approve/Reject POST submission from the review
     * queue (see renderQueue()) - applies the same action and reason to
     * every selected workflow in one pass, since staff working through
     * a backlog need to clear straightforward cases without opening
     * each one individually. Every row in the queue is already in
     * QualityReview (pendingReviews() guarantees that), so unlike
     * handleSubmission() there is no per-row status check - only
     * whether each row's workflow type actually supports the requested
     * action (Annual Return has no Reject action).
     *
     * @return array{0:string,1:string}|null
     */
    private function handleBulkAction(int $userId): ?array
    {
        if (! isset($_POST[self::BULK_NONCE_FIELD])) {
            return null;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::BULK_NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::BULK_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_TRANSITION)) {
            return ['error', __('You are not permitted to review applications.', 'bizupkeep-workflow')];
        }

        $bulkAction = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';

        if (! in_array($bulkAction, [self::ACTION_APPROVE, self::ACTION_REJECT], true)) {
            return null;
        }

        $reason = isset($_POST['bulk_reason']) ? sanitize_textarea_field(wp_unslash($_POST['bulk_reason'])) : '';

        if ($bulkAction === self::ACTION_REJECT && trim($reason) === '') {
            return ['error', __('A reason is required to bulk reject applications.', 'bizupkeep-workflow')];
        }

        $uuids = isset($_POST['workflow_uuids']) && is_array($_POST['workflow_uuids'])
            ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($_POST['workflow_uuids']))))
            : [];

        if ($uuids === []) {
            return ['error', __('Select at least one application.', 'bizupkeep-workflow')];
        }

        $succeeded = 0;
        $errors = [];

        foreach ($uuids as $uuid) {
            $workflow = $this->workflows->find($uuid);

            if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
                $errors[] = __('An application could not be found.', 'bizupkeep-workflow');

                continue;
            }

            $isBulkReject = $bulkAction === self::ACTION_REJECT;
            $isRejectable = in_array($workflow->getWorkflowType(), self::REJECTABLE_TYPES, true);

            if ($isBulkReject && ! $isRejectable) {
                $errors[] = sprintf(
                    /* translators: %s: workflow type label, e.g. "Annual Return" */
                    __('%s applications cannot be rejected.', 'bizupkeep-workflow'),
                    $this->typeLabel($workflow->getWorkflowType())
                );

                continue;
            }

            $context = $bulkAction === self::ACTION_APPROVE ? ['reviewed_by' => $this->currentUserLabel()] : [];

            try {
                $this->serviceFor($workflow->getWorkflowType())
                    ->performAction($uuid, $bulkAction, $userId, $reason, $context);

                $succeeded++;
            } catch (ValidationException | PreconditionFailedException | InvalidTransitionException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $summary = $bulkAction === self::ACTION_APPROVE
            ? sprintf(
                /* translators: %d: number of applications approved */
                _n('%d application approved.', '%d applications approved.', $succeeded, 'bizupkeep-workflow'),
                $succeeded
            )
            : sprintf(
                /* translators: %d: number of applications rejected */
                _n('%d application rejected.', '%d applications rejected.', $succeeded, 'bizupkeep-workflow'),
                $succeeded
            );

        if ($errors !== []) {
            $summary .= ' ' . sprintf(
                /* translators: %s: semicolon-separated list of per-row error messages */
                __('Some rows were skipped: %s', 'bizupkeep-workflow'),
                implode('; ', array_slice($errors, 0, 5))
            );

            return [$succeeded > 0 ? 'warning' : 'error', $summary];
        }

        return ['success', $summary];
    }

    /**
     * Handle the bulk document-upload POST submission from the review
     * queue (see renderQueue()) - attaches ONE uploaded file to every
     * selected application's company folder, for a document that
     * genuinely applies to several cases at once (e.g. a shared
     * compliance notice). Reuses the same checkbox selection and nonce
     * as handleBulkAction(), distinguished by its own submit-button
     * name ('bizupkeep_bulk_upload') rather than 'bulk_action'.
     *
     * @return array{0:string,1:string}|null
     */
    private function handleBulkUpload(int $userId): ?array
    {
        if (! isset($_POST['bizupkeep_bulk_upload'])) {
            return null;
        }

        $nonce = isset($_POST[self::BULK_NONCE_FIELD])
            ? sanitize_text_field(wp_unslash($_POST[self::BULK_NONCE_FIELD]))
            : '';

        if (! wp_verify_nonce($nonce, self::BULK_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_TRANSITION)) {
            return ['error', __('You are not permitted to upload documents.', 'bizupkeep-workflow')];
        }

        $categoryRaw = isset($_POST['bulk_upload_category'])
            ? sanitize_text_field(wp_unslash($_POST['bulk_upload_category']))
            : '';
        $category = null;

        foreach (DocumentCategory::cases() as $case) {
            if ($case->value === $categoryRaw) {
                $category = $case;

                break;
            }
        }

        if ($category === null) {
            return ['error', __('Please choose a document type.', 'bizupkeep-workflow')];
        }

        $file = $this->validateUploadedFile('bulk_upload_document');

        if ($file === null) {
            return ['error', __(
                'That upload could not be processed - please check the file (PDF, JPG or PNG, max 10MB) and try again.',
                'bizupkeep-workflow'
            )];
        }

        $uuids = isset($_POST['workflow_uuids']) && is_array($_POST['workflow_uuids'])
            ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($_POST['workflow_uuids']))))
            : [];

        if ($uuids === []) {
            return ['error', __('Select at least one application.', 'bizupkeep-workflow')];
        }

        $succeeded = 0;
        $errors = [];

        foreach ($uuids as $uuid) {
            $workflow = $this->workflows->find($uuid);

            if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
                $errors[] = __('An application could not be found.', 'bizupkeep-workflow');

                continue;
            }

            try {
                $company = $this->companies->getCompany($workflow->getSubjectUuid());
            } catch (CompanyNotFoundException) {
                $errors[] = __('An application\'s company record could not be found.', 'bizupkeep-workflow');

                continue;
            }

            try {
                $this->documents->uploadDocument(
                    'company',
                    $company->getUuid(),
                    $file['name'],
                    $category,
                    $file['tmp_name'],
                    $file['name'],
                    $userId
                );

                $succeeded++;
            } catch (\Throwable $exception) {
                $errors[] = sprintf(
                    /* translators: %s: company name */
                    __('Upload failed for %s.', 'bizupkeep-workflow'),
                    $company->getCompanyName()
                );
            }
        }

        $summary = sprintf(
            /* translators: %d: number of applications the document was attached to */
            _n(
                'Document attached to %d application.',
                'Document attached to %d applications.',
                $succeeded,
                'bizupkeep-workflow'
            ),
            $succeeded
        );

        if ($errors !== []) {
            $summary .= ' ' . sprintf(
                /* translators: %s: semicolon-separated list of per-row error messages */
                __('Some rows were skipped: %s', 'bizupkeep-workflow'),
                implode('; ', array_slice($errors, 0, 5))
            );

            return [$succeeded > 0 ? 'warning' : 'error', $summary];
        }

        return ['success', $summary];
    }

    /**
     * Handle the "Update Registration Number" POST submission (see
     * renderRegistrationNumberForm()) - lets staff record a company's
     * real CIPC registration number once issued, replacing the
     * PENDING-{uuid} placeholder every company starts with. No admin
     * screen could do this before - CompanyService::updateCompany()
     * existed but nothing ever called it.
     *
     * updateCompany() takes a full CompanyData, not a partial patch, so
     * this rebuilds one from the company's own current values with
     * only the registration number replaced - it does not touch
     * directors (updateCompany() never does, regardless).
     *
     * @return array{0:string,1:string}|null
     */
    private function handleUpdateRegistrationNumber(int $userId): ?array
    {
        if (! isset($_POST[self::REGISTRATION_NUMBER_NONCE_FIELD])) {
            return null;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::REGISTRATION_NUMBER_NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::REGISTRATION_NUMBER_NONCE_ACTION)) {
            return ['error', __('Security check failed. Please try again.', 'bizupkeep-workflow')];
        }

        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_TRANSITION)) {
            return ['error', __('You are not permitted to edit company details.', 'bizupkeep-workflow')];
        }

        $workflowUuid = isset($_POST['workflow']) ? sanitize_text_field(wp_unslash($_POST['workflow'])) : '';
        $newNumber = isset($_POST['registration_number'])
            ? sanitize_text_field(wp_unslash($_POST['registration_number']))
            : '';

        if (trim($newNumber) === '') {
            return ['error', __('Registration number cannot be empty.', 'bizupkeep-workflow')];
        }

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            return ['error', __('That application could not be found.', 'bizupkeep-workflow')];
        }

        try {
            $company = $this->companies->getCompany($workflow->getSubjectUuid());
        } catch (CompanyNotFoundException) {
            return ['error', __('That application\'s company record could not be found.', 'bizupkeep-workflow')];
        }

        $address = $company->getRegisteredAddress();

        try {
            $this->companies->updateCompany(new CompanyData(
                $company->getUuid(),
                $company->getClientId(),
                $newNumber,
                $company->getCompanyName(),
                $company->getCompanyType(),
                $company->getStatus(),
                new AddressData(
                    $address->getAddressLine1(),
                    $address->getAddressLine2(),
                    $address->getSuburb(),
                    $address->getCity(),
                    $address->getProvince(),
                    $address->getPostalCode(),
                    $address->getCountry()
                ),
                incorporationDate: $company->getIncorporationDate()
            ));
        } catch (InvalidCompanyException $exception) {
            return ['error', $exception->getMessage()];
        }

        return ['success', __('Registration number updated.', 'bizupkeep-workflow')];
    }

    /**
     * Render the queue of applications awaiting quality review.
     */
    private function renderQueue(): void
    {
        $pending = $this->pendingReviews();

        if ($pending === []) {
            echo '<p>'
                . esc_html__('No applications are currently awaiting quality review.', 'bizupkeep-workflow')
                . '</p>';

            return;
        }

        /*
         * Every row here already sits in QualityReview (pendingReviews()
         * guarantees that), so the whole table is wrapped in one form:
         * a checkbox per row, the two bulk-decision buttons, and a bulk
         * document upload let staff clear a backlog of straightforward
         * cases without opening each application individually.
         * handleBulkAction()/handleBulkUpload() re-validate every
         * selected row server-side (e.g. Annual Return can't be
         * bulk-rejected any more than it can be rejected one at a time).
         *
         * Bulk upload deliberately means ONE file attached to EVERY
         * selected application's company folder, not one file per row -
         * a plain HTML form can't sensibly collect a different file per
         * checked row. It's for a document that genuinely applies to
         * several cases at once (e.g. a shared compliance notice), not
         * a shortcut for uploading unrelated documents in one submit.
         */
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::BULK_NONCE_ACTION, self::BULK_NONCE_FIELD);

        echo '<p><label for="bizupkeep-bulk-reason">' . esc_html__(
            'Reason (required for Bulk Reject - applied to every selected row)',
            'bizupkeep-workflow'
        ) . '</label><br />'
            . '<textarea id="bizupkeep-bulk-reason" name="bulk_reason" rows="2" class="large-text"></textarea></p>';

        echo '<p>'
            . '<button type="submit" name="bulk_action" value="' . esc_attr(self::ACTION_APPROVE) . '" '
            . 'class="button button-primary">' . esc_html__('Bulk Approve Selected', 'bizupkeep-workflow')
            . '</button> '
            . '<button type="submit" name="bulk_action" value="' . esc_attr(self::ACTION_REJECT) . '" class="button">'
            . esc_html__('Bulk Reject Selected', 'bizupkeep-workflow') . '</button>'
            . '</p>';

        echo '<p><strong>' . esc_html__(
            'Upload one document to every selected application\'s company folder:',
            'bizupkeep-workflow'
        ) . '</strong></p>';

        echo '<p><label for="bizupkeep-bulk-upload-category">'
            . esc_html__('Document Type', 'bizupkeep-workflow') . '</label> ';
        echo '<select id="bizupkeep-bulk-upload-category" name="bulk_upload_category">';
        echo '<option value="">' . esc_html__('Select an option', 'bizupkeep-workflow') . '</option>';

        foreach (DocumentCategory::cases() as $case) {
            echo '<option value="' . esc_attr($case->value) . '">' . esc_html($case->label()) . '</option>';
        }

        echo '</select> ';
        echo '<label for="bizupkeep-bulk-upload-file">'
            . esc_html__('File (PDF, JPG or PNG, max 10MB)', 'bizupkeep-workflow') . '</label> '
            . '<input type="file" id="bizupkeep-bulk-upload-file" name="bulk_upload_document" '
            . 'accept=".pdf,.jpg,.jpeg,.png" /></p>';

        echo '<p><button type="submit" name="bizupkeep_bulk_upload" value="1" class="button">'
            . esc_html__('Bulk Upload to Selected', 'bizupkeep-workflow') . '</button></p>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>'
            . '<th class="check-column"></th>'
            . '<th>' . esc_html__('Type', 'bizupkeep-workflow') . '</th>'
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
                . '<td><input type="checkbox" name="workflow_uuids[]" value="' . esc_attr($summary->uuid) . '" /></td>'
                . '<td>' . esc_html($this->typeLabel($summary->workflowType)) . '</td>'
                . '<td>' . esc_html($companyName) . '</td>'
                . '<td>' . esc_html($regNumber) . '</td>'
                . '<td>' . esc_html($client) . '</td>'
                . '<td>' . esc_html($since) . '</td>'
                . '<td><a class="button button-primary" href="' . esc_url($reviewUrl) . '">'
                . esc_html__('Review', 'bizupkeep-workflow') . '</a></td>'
                . '</tr>';
        }

        echo '</tbody></table>';
        echo '</form>';
    }

    /**
     * Render a single application's detail: company info, type-specific
     * request details, uploaded documents (plus a staff upload form),
     * and - only while the application is actually awaiting quality
     * review - the Approve/Reject decision form. Unlike before staff
     * document upload existed, this now renders fully regardless of
     * the workflow's current status: staff need to reach a Completed
     * application's documents just as often as a QualityReview one
     * (e.g. to upload the final CIPC certificate after approval), and
     * the decision form is the only part that's genuinely specific to
     * the review stage.
     */
    private function renderDetail(string $workflowUuid): void
    {
        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            echo '<p>' . esc_html__('That application could not be found.', 'bizupkeep-workflow') . '</p>';

            return;
        }

        $backUrl = add_query_arg(['page' => self::SLUG], admin_url('admin.php'));
        echo '<p><a href="' . esc_url($backUrl) . '">&larr; '
            . esc_html__('Back to queue', 'bizupkeep-workflow') . '</a></p>';

        $company = null;

        try {
            $company = $this->companies->getCompany($workflow->getSubjectUuid());
        } catch (CompanyNotFoundException) {
            // Fall through - render what we can without company details.
        }

        echo '<p><span class="bizupkeep-workflow-type-badge">'
            . esc_html($this->typeLabel($workflow->getWorkflowType())) . '</span> '
            . '<span class="bizupkeep-status-pill">' . esc_html($workflow->getStatus()->label()) . '</span></p>';
        $companyLabel = $company?->getCompanyName() ?? __('(company record missing)', 'bizupkeep-workflow');
        echo '<h2>' . esc_html($companyLabel) . '</h2>';

        if ($company !== null) {
            $this->renderCompanyDetails($company);

            if ($this->authorization->can(get_current_user_id(), Capabilities::WORKFLOW_TRANSITION)) {
                $this->renderRegistrationNumberForm($workflow, $company);
            }

            $this->renderTypeSpecificDetails($workflow);
            $this->renderDocuments($workflow, $company);
            $this->renderUploadForm($workflow);
        }

        if (
            $workflow->getWorkflowType() === AnnualReturnDefinition::TYPE
            && in_array($workflow->getStatus(), [WorkflowStatus::Created, WorkflowStatus::AwaitingPayment], true)
        ) {
            $this->renderQuoteForm($workflow);
        }

        if ($workflow->getStatus() === WorkflowStatus::QualityReview) {
            $this->renderReviewForm($workflow);
        }

        if (
            ! $workflow->isTerminal()
            && $this->authorization->can(get_current_user_id(), Capabilities::WORKFLOW_MANAGE)
        ) {
            if ($this->canRollBack($workflow)) {
                $this->renderRollbackForm($workflow);
            }

            $this->renderForceStatusForm($workflow);
        }
    }

    /**
     * Whether WorkflowEngineInterface::rollback() could actually
     * succeed for this workflow - it needs at least one recorded
     * transition with a non-null "from" status (a workflow's very
     * first transition, Created -> its second status, has none - there
     * is nothing before it to roll back to). Checked here purely to
     * decide whether to show the button; WorkflowManager::rollback()
     * enforces the same rule regardless.
     */
    private function canRollBack(WorkflowInstance $workflow): bool
    {
        $history = $workflow->getHistory();
        $last = $history[count($history) - 1] ?? null;

        return $last !== null && $last->from !== null;
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
        $this->row(
            __('Registered Address', 'bizupkeep-workflow'),
            $company->getRegisteredAddress()->getFormattedAddress()
        );
        echo '</tbody></table>';
    }

    /**
     * A compact inline form for recording a company's real CIPC
     * registration number once issued, replacing the PENDING-{uuid}
     * placeholder every company starts with (see
     * bizupkeep_child_submit_new_registration() in the theme). Shown
     * regardless of the workflow's current status, same as the
     * document upload form below it - staff need to fix this just as
     * often on a Completed application as anywhere else.
     */
    private function renderRegistrationNumberForm(WorkflowInstance $workflow, Company $company): void
    {
        echo '<form method="post" style="margin:0 0 1.5em;">';
        wp_nonce_field(self::REGISTRATION_NUMBER_NONCE_ACTION, self::REGISTRATION_NUMBER_NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';
        echo '<label for="bizupkeep-registration-number">'
            . esc_html__('Update Registration Number:', 'bizupkeep-workflow') . '</label> '
            . '<input type="text" id="bizupkeep-registration-number" name="registration_number" '
            . 'value="' . esc_attr($company->getRegistrationNumber()) . '" size="30" required /> '
            . '<button type="submit" class="button">' . esc_html__('Save', 'bizupkeep-workflow') . '</button>';
        echo '</form>';
    }

    /**
     * Render what's actually being requested, using the metadata each
     * type's Service records at start() - proposed names for a
     * Registration, which amendment type(s)/director changes/new
     * address for an Amendment, the filing year for an Annual Return.
     */
    private function renderTypeSpecificDetails(WorkflowInstance $workflow): void
    {
        $metadata = $workflow->getMetadata();

        if ($workflow->getWorkflowType() === CompanyRegistrationDefinition::TYPE) {
            $names = $this->stringList($metadata['proposed_names'] ?? null);

            if ($names !== []) {
                echo '<h3>' . esc_html__('Proposed Company Names', 'bizupkeep-workflow') . '</h3><ol>';
                foreach ($names as $name) {
                    echo '<li>' . esc_html($name) . '</li>';
                }
                echo '</ol>';
            }

            $clientNotes = is_string($metadata['client_notes'] ?? null) ? $metadata['client_notes'] : '';

            if (trim($clientNotes) !== '') {
                echo '<h3>' . esc_html__('Client Notes', 'bizupkeep-workflow') . '</h3>';
                echo '<p>' . nl2br(esc_html($clientNotes)) . '</p>';
            }

            return;
        }

        if ($workflow->getWorkflowType() === AnnualReturnDefinition::TYPE) {
            echo '<h3>' . esc_html__('Filing Details', 'bizupkeep-workflow') . '</h3>';

            $filings = $this->filingsFromMetadata($metadata);

            echo '<table class="form-table"><tbody>'
                . '<tr><th>' . esc_html__('Financial Year', 'bizupkeep-workflow') . '</th>'
                . '<th>' . esc_html__('Turnover', 'bizupkeep-workflow') . '</th></tr>';

            foreach ($filings as $filing) {
                $year = (int) ($filing['financial_year'] ?? 0);
                $turnover = $filing['turnover'] ?? null;
                $turnoverLabel = is_numeric($turnover)
                    ? number_format((float) $turnover, 2)
                    : __('(not provided)', 'bizupkeep-workflow');

                echo '<tr><td>' . esc_html((string) $year) . '</td><td>' . esc_html($turnoverLabel) . '</td></tr>';
            }

            echo '</tbody></table>';

            echo '<table class="form-table"><tbody>';

            $clientNotes = is_string($metadata['client_notes'] ?? null) ? $metadata['client_notes'] : '';

            if (trim($clientNotes) !== '') {
                $this->row(__('Client Notes', 'bizupkeep-workflow'), $clientNotes);
            }

            $quoteAmount = $metadata['quote_amount'] ?? null;

            if (is_numeric($quoteAmount) && (float) $quoteAmount > 0.0) {
                $this->row(__('Quoted Amount', 'bizupkeep-workflow'), number_format((float) $quoteAmount, 2));

                $quoteNotes = is_string($metadata['quote_notes'] ?? null) ? $metadata['quote_notes'] : '';

                if (trim($quoteNotes) !== '') {
                    $this->row(__('Quote Notes', 'bizupkeep-workflow'), $quoteNotes);
                }
            }

            echo '</tbody></table>';

            return;
        }

        // Company Amendment.
        $types = array_values(array_intersect(
            $this->stringList($metadata['amendment_types'] ?? null),
            CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES
        ));

        echo '<h3>' . esc_html__('Requested Changes', 'bizupkeep-workflow') . '</h3><ul>';
        foreach ($types as $type) {
            echo '<li>' . esc_html($this->amendmentTypeLabel($type)) . '</li>';
        }
        echo '</ul>';

        if (in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME, $types, true)) {
            $names = $this->stringList($metadata['proposed_names'] ?? null);
            echo '<p><strong>' . esc_html__('Proposed names:', 'bizupkeep-workflow') . '</strong> '
                . esc_html(implode('; ', $names)) . '</p>';
        }

        if (in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_ADDRESS, $types, true)) {
            $address = is_array($metadata['new_address'] ?? null) ? $metadata['new_address'] : [];
            $parts = array_filter([
                $address['address_line_1'] ?? '',
                $address['address_line_2'] ?? '',
                $address['suburb'] ?? '',
                $address['city'] ?? '',
                $address['province'] ?? '',
                $address['postal_code'] ?? '',
            ]);
            echo '<p><strong>' . esc_html__('New address:', 'bizupkeep-workflow') . '</strong> '
                . esc_html(implode(', ', $parts)) . '</p>';
        }

        if (in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_DIRECTOR, $types, true)) {
            $changes = is_array($metadata['director_changes'] ?? null) ? $metadata['director_changes'] : [];
            echo '<p><strong>' . esc_html__('Director changes:', 'bizupkeep-workflow') . '</strong></p><ul>';
            foreach ($changes as $change) {
                echo '<li>' . esc_html($this->directorChangeLabel($change)) . '</li>';
            }
            echo '</ul>';
        }
    }

    /**
     * @param mixed $change
     */
    private function directorChangeLabel($change): string
    {
        if (! is_array($change)) {
            return '';
        }

        if (($change['action'] ?? '') === 'remove') {
            return sprintf(
                /* translators: %s: director's full name */
                __('Remove: %s', 'bizupkeep-workflow'),
                (string) ($change['name'] ?? '')
            );
        }

        return sprintf(
            /* translators: %s: director's full name */
            __('Add: %s', 'bizupkeep-workflow'),
            trim((string) ($change['first_name'] ?? '') . ' ' . (string) ($change['last_name'] ?? ''))
        );
    }

    private function amendmentTypeLabel(string $type): string
    {
        return match ($type) {
            CompanyAmendmentDefinition::AMENDMENT_TYPE_DIRECTOR => __('Director amendment', 'bizupkeep-workflow'),
            CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME => __('Name change', 'bizupkeep-workflow'),
            CompanyAmendmentDefinition::AMENDMENT_TYPE_ADDRESS => __('Address change', 'bizupkeep-workflow'),
            default => $type,
        };
    }

    private function row(string $label, string $value): void
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }

    /**
     * List every document for the company, each with its full version
     * history (not just the current version) so staff can download,
     * delete, or replace any individual version - correcting one bad
     * upload without losing the rest of a document's history.
     */
    private function renderDocuments(WorkflowInstance $workflow, Company $company): void
    {
        $documents = $this->documents->getDocumentsForOwner('company', $company->getUuid());

        echo '<h3>' . esc_html__('Submitted Documents', 'bizupkeep-workflow') . '</h3>';

        if ($documents === []) {
            echo '<p>' . esc_html__('No documents have been uploaded for this company.', 'bizupkeep-workflow') . '</p>';

            return;
        }

        foreach ($documents as $document) {
            $versions = $document->getVersions(); // Most recent first.

            echo '<p><strong>' . esc_html($document->getCategory()->label()) . '</strong> &mdash; '
                . esc_html($document->getName()) . '</p><ul>';

            foreach ($versions as $version) {
                $downloadUrl = add_query_arg(
                    [
                        'page' => self::SLUG,
                        'workflow' => $workflow->getUuid(),
                        'download' => $document->getUuid(),
                        'version' => $version->uuid,
                    ],
                    admin_url('admin.php')
                );

                echo '<li>'
                    . '<a href="' . esc_url($downloadUrl) . '">'
                    . esc_html(sprintf(
                        /* translators: 1: version number, 2: upload date/time */
                        __('Version %1$d (%2$s)', 'bizupkeep-workflow'),
                        $version->versionNumber,
                        wp_date('Y-m-d H:i', $version->uploadedAt->getTimestamp())
                    ))
                    . '</a>';

                if (count($versions) > 1) {
                    echo ' ';
                    $this->renderDeleteVersionButton($workflow, $document->getUuid(), $version->uuid);
                }

                echo '</li>';
            }

            echo '</ul>';

            $this->renderReplaceVersionForm($workflow, $document->getUuid());
        }
    }

    /**
     * A small inline form deleting a single version, shown next to it
     * in renderDocuments() - only ever rendered when the document has
     * more than one version (handleVersionDelete()/DocumentService
     * refuse to remove the last one regardless, but hiding the button
     * avoids a guaranteed-to-fail submission).
     */
    private function renderDeleteVersionButton(
        WorkflowInstance $workflow,
        string $documentUuid,
        string $versionUuid
    ): void {
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\''
            . esc_js(__('Delete this version? This cannot be undone.', 'bizupkeep-workflow'))
            . '\');">';
        wp_nonce_field(self::DELETE_VERSION_NONCE_ACTION, self::DELETE_VERSION_NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';
        echo '<input type="hidden" name="target_document" value="' . esc_attr($documentUuid) . '" />';
        echo '<input type="hidden" name="version" value="' . esc_attr($versionUuid) . '" />';
        echo '<button type="submit" class="button-link" style="color:#b32d2e;">'
            . esc_html__('Delete', 'bizupkeep-workflow') . '</button>';
        echo '</form>';
    }

    /**
     * A compact per-document "replace" upload, posting to the same
     * handler as renderUploadForm() (handleDocumentUpload()) but with
     * 'target_document' set, so the file is attached as a new version
     * of this document instead of creating a new one under a category
     * picker.
     */
    private function renderReplaceVersionForm(WorkflowInstance $workflow, string $documentUuid): void
    {
        echo '<form method="post" enctype="multipart/form-data" style="margin:0 0 1.5em;">';
        wp_nonce_field(self::UPLOAD_NONCE_ACTION, self::UPLOAD_NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';
        echo '<input type="hidden" name="target_document" value="' . esc_attr($documentUuid) . '" />';
        echo '<label>' . esc_html__('Replace with a new version:', 'bizupkeep-workflow') . ' '
            . '<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required /></label> '
            . '<button type="submit" class="button">' . esc_html__('Upload New Version', 'bizupkeep-workflow')
            . '</button>';
        echo '</form>';
    }

    /**
     * A staff-facing upload form, attaching a document to the
     * application's company under whatever category is chosen (e.g.
     * the final CIPC registration/amendment certificate once
     * Completed). Available regardless of the workflow's current
     * status - see renderDetail().
     */
    private function renderUploadForm(WorkflowInstance $workflow): void
    {
        echo '<h3>' . esc_html__('Upload a Document', 'bizupkeep-workflow') . '</h3>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::UPLOAD_NONCE_ACTION, self::UPLOAD_NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';

        echo '<p><label for="bizupkeep-upload-category">'
            . esc_html__('Document Type', 'bizupkeep-workflow') . '</label><br />';
        echo '<select id="bizupkeep-upload-category" name="category" required>';
        echo '<option value="">' . esc_html__('Select an option', 'bizupkeep-workflow') . '</option>';

        foreach (DocumentCategory::cases() as $case) {
            echo '<option value="' . esc_attr($case->value) . '">' . esc_html($case->label()) . '</option>';
        }

        echo '</select></p>';

        echo '<p><label for="bizupkeep-upload-file">'
            . esc_html__('File (PDF, JPG or PNG, max 10MB)', 'bizupkeep-workflow') . '</label><br />'
            . '<input type="file" id="bizupkeep-upload-file" name="document" '
            . 'accept=".pdf,.jpg,.jpeg,.png" required /></p>';

        echo '<p><button type="submit" class="button button-primary">'
            . esc_html__('Upload', 'bizupkeep-workflow') . '</button></p>';

        echo '</form>';
    }

    /**
     * The staff-facing "Send Quote" / "Revise Quote" form for an Annual
     * Return application - see renderDetail() and handleSendQuote().
     * Shown while Created (nothing quoted yet - firing this is what
     * moves the workflow to AwaitingPayment, and the client can't pay
     * anything until it does) or while AwaitingPayment (already
     * quoted, but not yet paid - firing this just overwrites the
     * amount/notes via AnnualReturnDefinition::ACTION_REVISE_QUOTE, a
     * same-status transition). Once payment is confirmed
     * (Processing/Completed) this form no longer appears at all -
     * revising a quote after the client has already paid isn't
     * something either action supports.
     */
    private function renderQuoteForm(WorkflowInstance $workflow): void
    {
        $isRevision = $workflow->getStatus() === WorkflowStatus::AwaitingPayment;
        $metadata = $workflow->getMetadata();
        $currentAmount = $isRevision && is_numeric($metadata['quote_amount'] ?? null)
            ? (float) $metadata['quote_amount']
            : null;
        $currentNotes = $isRevision && is_string($metadata['quote_notes'] ?? null)
            ? $metadata['quote_notes']
            : '';

        echo '<h3>' . esc_html(
            $isRevision ? __('Revise Quote', 'bizupkeep-workflow') : __('Send Quote', 'bizupkeep-workflow')
        ) . '</h3>';
        echo '<p>' . esc_html(
            $isRevision
                ? __('The client has not paid yet - change the amount and/or notes below.', 'bizupkeep-workflow')
                : __(
                    'Check this filing on CIPC, then enter what to charge - the client can only pay once quoted.',
                    'bizupkeep-workflow'
                )
        ) . '</p>';
        echo '<form method="post">';
        wp_nonce_field(self::QUOTE_NONCE_ACTION, self::QUOTE_NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';

        echo '<p><label for="bizupkeep-quote-amount">'
            . esc_html__('Quote Amount (ZAR)', 'bizupkeep-workflow') . '</label><br />'
            . '<input type="number" id="bizupkeep-quote-amount" name="quote_amount" '
            . 'min="0.01" step="0.01" required'
            . (null !== $currentAmount ? ' value="' . esc_attr((string) $currentAmount) . '"' : '')
            . ' /></p>';

        echo '<p><label for="bizupkeep-quote-notes">'
            . esc_html__('Notes to Client (optional)', 'bizupkeep-workflow') . '</label><br />'
            . '<textarea id="bizupkeep-quote-notes" name="quote_notes" rows="3" class="large-text">'
            . esc_textarea($currentNotes) . '</textarea></p>';

        echo '<p><button type="submit" class="button button-primary">'
            . esc_html(
                $isRevision ? __('Revise Quote', 'bizupkeep-workflow') : __('Send Quote', 'bizupkeep-workflow')
            ) . '</button></p>';

        echo '</form>';
    }

    private function renderReviewForm(WorkflowInstance $workflow): void
    {
        $canReject = in_array($workflow->getWorkflowType(), self::REJECTABLE_TYPES, true);
        $canRejectName = $this->canRejectName($workflow);

        echo '<h3>' . esc_html__('Decision', 'bizupkeep-workflow') . '</h3>';
        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';

        echo '<p><label for="bizupkeep-reason">' . esc_html(
            $canReject || $canRejectName
                ? __('Notes (required to reject)', 'bizupkeep-workflow')
                : __('Notes (optional)', 'bizupkeep-workflow')
        ) . '</label><br />'
            . '<textarea id="bizupkeep-reason" name="reason" rows="4" class="large-text"></textarea></p>';

        echo '<p>'
            . '<button type="submit" name="bizupkeep_action" value="'
            . esc_attr(self::ACTION_APPROVE) . '" class="button button-primary">'
            . esc_html__('Approve', 'bizupkeep-workflow') . '</button> ';

        if ($canRejectName) {
            echo '<button type="submit" name="bizupkeep_action" value="'
                . esc_attr(self::ACTION_REJECT_NAME) . '" class="button">'
                . esc_html__('Reject - Name Not Approved', 'bizupkeep-workflow') . '</button> ';
        }

        if ($canReject) {
            echo '<button type="submit" name="bizupkeep_action" value="'
                . esc_attr(self::ACTION_REJECT) . '" class="button">'
                . esc_html__('Reject', 'bizupkeep-workflow') . '</button>';
        }

        echo '</p></form>';
    }

    /**
     * Whether "Reject - Name Not Approved" applies to this specific
     * workflow instance. Company Registration always involves a
     * proposed company name, so it's unconditional there. Company
     * Amendment only involves one when this particular application
     * bundled a name change - checked against its own metadata, not
     * just its type, since one Amendment instance might be a
     * director-only or address-only change with no name to reject.
     * Annual Return never involves a proposed name at all.
     *
     * CompanyAmendmentGuard::guardRejectName() enforces the same rule
     * at the transition itself - this is the read-side check that
     * decides whether to offer the button/allow the request in the
     * first place, not the source of truth for whether it's permitted.
     */
    private function canRejectName(WorkflowInstance $workflow): bool
    {
        if ($workflow->getWorkflowType() === CompanyRegistrationDefinition::TYPE) {
            return true;
        }

        if ($workflow->getWorkflowType() === CompanyAmendmentDefinition::TYPE) {
            $types = $workflow->getMetadata()['amendment_types'] ?? [];

            return is_array($types) && in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME, $types, true);
        }

        return false;
    }

    /**
     * "Undo Last Step" - a single button reverting the application to
     * whatever status it was in immediately before its most recent
     * transition (WorkflowEngineInterface::rollback()). Only shown when
     * canRollBack() confirms there is actually a prior status to revert
     * to. Less powerful than Override Status below it (can only ever
     * land on the exact previous status, not any status chosen freely)
     * but shares the same WORKFLOW_MANAGE gate, since it equally
     * bypasses the transition guard.
     */
    private function renderRollbackForm(WorkflowInstance $workflow): void
    {
        echo '<h3>' . esc_html__('Undo Last Step', 'bizupkeep-workflow') . '</h3>';
        echo '<form method="post">';
        wp_nonce_field(self::ROLLBACK_NONCE_ACTION, self::ROLLBACK_NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';

        echo '<p><label for="bizupkeep-rollback-reason">'
            . esc_html__('Reason (required)', 'bizupkeep-workflow') . '</label><br />'
            . '<textarea id="bizupkeep-rollback-reason" name="rollback_reason" rows="2" class="large-text" '
            . 'required></textarea></p>';

        echo '<p><button type="submit" class="button">'
            . esc_html__('Undo Last Step', 'bizupkeep-workflow') . '</button></p>';
        echo '</form>';
    }

    /**
     * The staff "unstick a workflow" escape hatch - forces the
     * application directly to a chosen status, bypassing its normal
     * guarded transitions entirely. Shown for any non-terminal status
     * (see renderDetail()), gated by the stricter WORKFLOW_MANAGE
     * capability rather than WORKFLOW_TRANSITION, since this is
     * deliberately outside the reviewed lifecycle every other form on
     * this page respects.
     */
    private function renderForceStatusForm(WorkflowInstance $workflow): void
    {
        echo '<h3>' . esc_html__('Override Status (Advanced)', 'bizupkeep-workflow') . '</h3>';
        echo '<p>' . esc_html__(
            'Force this application directly to a different status, bypassing its normal approval flow. Use only to unstick an application that cannot otherwise recover - every use is recorded in its history.',
            'bizupkeep-workflow'
        ) . '</p>';
        echo '<form method="post">';
        wp_nonce_field(self::FORCE_STATUS_NONCE_ACTION, self::FORCE_STATUS_NONCE_FIELD);
        echo '<input type="hidden" name="workflow" value="' . esc_attr($workflow->getUuid()) . '" />';

        echo '<p><label for="bizupkeep-force-status">'
            . esc_html__('New Status', 'bizupkeep-workflow') . '</label><br />';
        echo '<select id="bizupkeep-force-status" name="force_status" required>';
        echo '<option value="">' . esc_html__('Select a status', 'bizupkeep-workflow') . '</option>';

        foreach (WorkflowStatus::cases() as $status) {
            if ($status === $workflow->getStatus()) {
                continue;
            }

            echo '<option value="' . esc_attr($status->value) . '">' . esc_html($status->label()) . '</option>';
        }

        echo '</select></p>';

        echo '<p><label for="bizupkeep-force-reason">'
            . esc_html__('Reason (required)', 'bizupkeep-workflow') . '</label><br />'
            . '<textarea id="bizupkeep-force-reason" name="force_reason" rows="3" class="large-text" required>'
            . '</textarea></p>';

        echo '<p><button type="submit" class="button">'
            . esc_html__('Override Status', 'bizupkeep-workflow') . '</button></p>';

        echo '</form>';
    }

    /**
     * Stream a submitted document to the browser - a specific version
     * if the 'version' query arg names one belonging to the document,
     * otherwise its current version - re-verifying the document
     * actually belongs to the company under review before serving it.
     */
    private function streamDocument(int $userId, string $workflowUuid, string $documentUuid): void
    {
        if (! $this->authorization->can($userId, Capabilities::WORKFLOW_VIEW)) {
            wp_die(esc_html__('You are not permitted to access this document.', 'bizupkeep-workflow'));
        }

        $workflow = $this->workflows->find($workflowUuid);

        if ($workflow === null || ! in_array($workflow->getWorkflowType(), self::REVIEWED_TYPES, true)) {
            wp_die(esc_html__('That document could not be found.', 'bizupkeep-workflow'));
        }

        try {
            $company = $this->companies->getCompany($workflow->getSubjectUuid());
            $document = $this->documents->getDocument($documentUuid);
        } catch (CompanyNotFoundException | DocumentNotFoundException) {
            wp_die(esc_html__('That document could not be found.', 'bizupkeep-workflow'));
        }

        if ($document->getOwnerType() !== 'company' || $document->getOwnerUuid() !== $company->getUuid()) {
            wp_die(esc_html__('That document does not belong to this application.', 'bizupkeep-workflow'));
        }

        $requestedVersionUuid = $this->param('version');
        $version = null;

        if ($requestedVersionUuid !== '') {
            foreach ($document->getVersions() as $candidate) {
                if ($candidate->uuid === $requestedVersionUuid) {
                    $version = $candidate;

                    break;
                }
            }
        }

        $version ??= $document->getCurrentVersion();

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
        $pending = [];

        foreach (self::REVIEWED_TYPES as $type) {
            $summaries = $this->workflows->summariesByStatus(
                $type,
                WorkflowStatus::QualityReview,
                self::SCAN_LIMIT
            );

            foreach ($summaries as $summary) {
                $pending[] = $summary;
            }
        }

        usort(
            $pending,
            static fn (WorkflowSummary $a, WorkflowSummary $b): int =>
                ($a->updatedAt ?? $a->createdAt) <=> ($b->updatedAt ?? $b->createdAt)
        );

        return $pending;
    }

    /**
     * Resolve the workflow type's own Service class, the only path
     * that may touch the workflow engine for that type.
     */
    private function serviceFor(string $workflowType): WorkflowTypeServiceInterface
    {
        $service = match ($workflowType) {
            CompanyRegistrationDefinition::TYPE => $this->registrations,
            CompanyAmendmentDefinition::TYPE => $this->amendments,
            AnnualReturnDefinition::TYPE => $this->annualReturns,
            default => null,
        };

        if ($service === null) {
            throw new RuntimeException(
                esc_html("Quality Review does not support workflow type \"{$workflowType}\".")
            );
        }

        return $service;
    }

    private function typeLabel(string $workflowType): string
    {
        return match ($workflowType) {
            CompanyRegistrationDefinition::TYPE => __('Company Registration', 'bizupkeep-workflow'),
            CompanyAmendmentDefinition::TYPE => __('Company Amendment', 'bizupkeep-workflow'),
            AnnualReturnDefinition::TYPE => __('Annual Return', 'bizupkeep-workflow'),
            default => $workflowType,
        };
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

    /**
     * Normalize a metadata value that should be a list of strings
     * (e.g. proposed_names), tolerating whatever shape actually ended
     * up in a workflow's JSON-decoded metadata.
     *
     * @param mixed $value
     *
     * @return array<int,string>
     */
    private function stringList($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? $item : '', $value),
            static fn (string $item): bool => trim($item) !== ''
        ));
    }

    /**
     * Normalize an Annual Return workflow's metadata into a list of
     * {financial_year, turnover} filings, tolerating the old shape (a
     * single flat `financial_year` int, from before an application
     * could cover multiple years) so an application started before
     * this display existed still shows something sensible. Mirrors
     * AnnualReturnService::filingsFromMetadata() - kept separate since
     * this is a read-side display concern, not a workflow-engine rule.
     *
     * @param array<string,mixed> $metadata
     *
     * @return array<int,array<string,mixed>>
     */
    private function filingsFromMetadata(array $metadata): array
    {
        if (isset($metadata['filings']) && is_array($metadata['filings'])) {
            return $metadata['filings'];
        }

        if (isset($metadata['financial_year'])) {
            return [['financial_year' => (int) $metadata['financial_year'], 'turnover' => null]];
        }

        return [];
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
