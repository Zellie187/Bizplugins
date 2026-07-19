<?php

declare(strict_types=1);

namespace BizHub\Workflow\Http\Controllers;

use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Framework\Logging\Logger;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\Exceptions\PreconditionFailedException;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;
use BizHub\Workflow\Http\Requests\CompanyRegistrationActionRequest;
use BizHub\Workflow\Http\Requests\StartCompanyRegistrationRequest;
use BizHub\Workflow\Policies\Capabilities;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationService;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for the Company Registration workflow.
 *
 * Thin by design (BH-WORKFLOW-SPEC-001 section 4): every method here
 * only verifies authorization, shapes/validates request input via a
 * Request class, delegates to CompanyRegistrationService, and maps the
 * result (or a known exception) to an HTTP response. It never touches
 * the workflow engine, a repository or the database directly.
 *
 * @package BizHub\Workflow\Http\Controllers
 */
final class CompanyRegistrationController
{
    public function __construct(
        private readonly CompanyRegistrationService $service,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly Logger $logger,
    ) {
    }

    /**
     * POST /bizupkeep-workflow/v1/company-registrations
     */
    public function start(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->can(Capabilities::WORKFLOW_MANAGE)) {
            return $this->forbidden();
        }

        try {
            $input = StartCompanyRegistrationRequest::fromRestRequest($request);

            $workflow = $this->service->start($input->companyUuid, get_current_user_id());

            return new WP_REST_Response($this->present($workflow), 201);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * GET /bizupkeep-workflow/v1/company-registrations/{uuid}
     */
    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->can(Capabilities::WORKFLOW_VIEW)) {
            return $this->forbidden();
        }

        try {
            $workflow = $this->service->find((string) $request->get_param('uuid'));

            return new WP_REST_Response($this->present($workflow, includeHistory: true), 200);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * POST /bizupkeep-workflow/v1/company-registrations/{uuid}/actions
     */
    public function performAction(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->can(Capabilities::WORKFLOW_TRANSITION)) {
            return $this->forbidden();
        }

        try {
            $input = CompanyRegistrationActionRequest::fromRestRequest($request);

            $workflow = $this->service->performAction(
                (string) $request->get_param('uuid'),
                $input->action,
                get_current_user_id(),
                $input->reason,
                $input->context
            );

            return new WP_REST_Response($this->present($workflow), 200);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * POST /bizupkeep-workflow/v1/company-registrations/{uuid}/rollback
     */
    public function rollback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->can(Capabilities::WORKFLOW_MANAGE)) {
            return $this->forbidden();
        }

        try {
            $reason = trim((string) ($request->get_param('reason') ?? ''));

            $workflow = $this->service->rollback(
                (string) $request->get_param('uuid'),
                get_current_user_id(),
                $reason
            );

            return new WP_REST_Response($this->present($workflow), 200);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function can(string $capability): bool
    {
        return $this->authorizationService->can(get_current_user_id(), $capability);
    }

    private function forbidden(): WP_Error
    {
        return new WP_Error(
            'bizupkeep_workflow_forbidden',
            __('You are not permitted to perform this action.', 'bizupkeep-workflow'),
            ['status' => 403]
        );
    }

    /**
     * Translate a caught exception into a safe REST response. Only
     * known, purpose-built exceptions are ever reflected to the
     * client; anything else is logged in full and reported generically,
     * per BH-WORKFLOW-SPEC-001 section 11.
     */
    private function handle(Throwable $exception): WP_Error
    {
        return match (true) {
            $exception instanceof ValidationException => new WP_Error(
                'bizupkeep_workflow_validation_failed',
                $exception->getMessage(),
                ['status' => 422, 'errors' => $exception->errors()]
            ),
            $exception instanceof WorkflowNotFoundException,
            $exception instanceof CompanyNotFoundException => new WP_Error(
                'bizupkeep_workflow_not_found',
                $exception->getMessage(),
                ['status' => 404]
            ),
            $exception instanceof PreconditionFailedException,
            $exception instanceof InvalidTransitionException => new WP_Error(
                'bizupkeep_workflow_conflict',
                $exception->getMessage(),
                ['status' => 409]
            ),
            default => $this->unexpected($exception),
        };
    }

    private function unexpected(Throwable $exception): WP_Error
    {
        $this->logger->error('bizupkeep_workflow.unexpected_error', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'user_id' => get_current_user_id(),
        ]);

        return new WP_Error(
            'bizupkeep_workflow_internal_error',
            __('An unexpected error occurred. Please try again or contact support.', 'bizupkeep-workflow'),
            ['status' => 500]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function present(\BizHub\Workflow\Entities\WorkflowInstance $workflow, bool $includeHistory = false): array
    {
        $payload = [
            'uuid' => $workflow->getUuid(),
            'workflow_type' => $workflow->getWorkflowType(),
            'subject_type' => $workflow->getSubjectType(),
            'subject_uuid' => $workflow->getSubjectUuid(),
            'status' => $workflow->getStatus()->value,
            'status_label' => $workflow->getStatus()->label(),
            'metadata' => $workflow->getMetadata(),
            'created_at' => $workflow->getCreatedAt()->format(DATE_ATOM),
            'updated_at' => $workflow->getUpdatedAt()?->format(DATE_ATOM),
            'completed_at' => $workflow->getCompletedAt()?->format(DATE_ATOM),
        ];

        if ($includeHistory) {
            $payload['history'] = array_map(
                static fn ($transition): array => [
                    'uuid' => $transition->uuid,
                    'from_status' => $transition->from?->value,
                    'to_status' => $transition->to->value,
                    'action' => $transition->action,
                    'actor_id' => $transition->actorId,
                    'reason' => $transition->reason,
                    'occurred_at' => $transition->occurredAt->format(DATE_ATOM),
                ],
                $workflow->getHistory()
            );
        }

        return $payload;
    }
}
