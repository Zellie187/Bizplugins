<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\CompanyRegistration;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Workflow\Contracts\WorkflowEngineInterface;
use BizHub\Workflow\Contracts\WorkflowTypeServiceInterface;
use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\DTO\RollbackWorkflowCommand;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;

/**
 * Service layer for the Company Registration workflow: the Controller
 * only ever talks to this class, which in turn is the only caller of
 * WorkflowEngineInterface for this workflow type, per BH-WORKFLOW-
 * SPEC-001 section 4's Controller -> Service -> Workflow Engine
 * layering.
 *
 * This is where cross-module business rules that are not the workflow
 * engine's concern live - e.g. that a Company Registration can only be
 * started for a Company that actually exists in BizHub\Companies.
 *
 * @package BizHub\Workflow\Workflows\CompanyRegistration
 */
final class CompanyRegistrationService implements WorkflowTypeServiceInterface
{
    /**
     * @var array<int,string>
     */
    private const ALLOWED_ACTIONS = [
        CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS,
        CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS,
        CompanyRegistrationDefinition::ACTION_REQUEST_PAYMENT,
        CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT,
        CompanyRegistrationDefinition::ACTION_START_QUALITY_REVIEW,
        CompanyRegistrationDefinition::ACTION_APPROVE,
        CompanyRegistrationDefinition::ACTION_ARCHIVE,
        CompanyRegistrationDefinition::ACTION_CANCEL,
        CompanyRegistrationDefinition::ACTION_REJECT,
    ];

    public function __construct(
        private readonly WorkflowEngineInterface $workflowEngine,
        private readonly CompanyServiceInterface $companyService,
    ) {
    }

    /**
     * Start a Company Registration workflow for an existing company.
     *
     * @param array<string,mixed> $metadata Optional workflow metadata, e.g.
     *                                       'proposed_names' (the client's up-to-4
     *                                       CIPC name choices, in order of preference).
     *
     * @throws \BizHub\Companies\Exceptions\CompanyNotFoundException If the company does not exist.
     */
    public function start(string $companyUuid, int $userId, array $metadata = []): WorkflowInstance
    {
        $company = $this->companyService->getCompany($companyUuid);

        return $this->workflowEngine->create(new CreateWorkflowCommand(
            CompanyRegistrationDefinition::TYPE,
            'company',
            $company->getUuid(),
            $userId,
            $metadata
        ));
    }

    /**
     * Perform a named action against a Company Registration workflow.
     *
     * @param array<string,mixed> $context
     *
     * @throws ValidationException      If $action is not a Company Registration action.
     * @throws WorkflowNotFoundException If the workflow does not exist or is not a
     *                                    Company Registration.
     */
    public function performAction(
        string $workflowUuid,
        string $action,
        int $userId,
        string $reason = '',
        array $context = []
    ): WorkflowInstance {
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new ValidationException(
                sprintf('"%s" is not a valid Company Registration action.', $action),
                ['action' => 'Unknown action.']
            );
        }

        $this->assertIsCompanyRegistration($this->workflowEngine->find($workflowUuid));

        return $this->workflowEngine->transition(
            new TransitionWorkflowCommand($workflowUuid, $action, $userId, $reason, $context)
        );
    }

    /**
     * Roll a Company Registration workflow back to its previous status.
     */
    public function rollback(string $workflowUuid, int $userId, string $reason): WorkflowInstance
    {
        $this->assertIsCompanyRegistration($this->workflowEngine->find($workflowUuid));

        return $this->workflowEngine->rollback(
            new RollbackWorkflowCommand($workflowUuid, $userId, $reason)
        );
    }

    /**
     * Retrieve a Company Registration workflow instance.
     *
     * @throws WorkflowNotFoundException
     */
    public function find(string $workflowUuid): WorkflowInstance
    {
        $workflow = $this->workflowEngine->find($workflowUuid);

        $this->assertIsCompanyRegistration($workflow);

        return $workflow;
    }

    /**
     * Retrieve a Company Registration workflow's full transition
     * history.
     *
     * @return array<int,\BizHub\Workflow\DTO\Transition>
     */
    public function historyFor(string $workflowUuid): array
    {
        $this->find($workflowUuid);

        return $this->workflowEngine->historyFor($workflowUuid);
    }

    /**
     * Guard against operating on a workflow instance that exists but
     * is of a different workflow type (e.g. its UUID was reused
     * against the wrong endpoint).
     */
    private function assertIsCompanyRegistration(WorkflowInstance $workflow): void
    {
        if ($workflow->getWorkflowType() !== CompanyRegistrationDefinition::TYPE) {
            throw WorkflowNotFoundException::forUuid($workflow->getUuid());
        }
    }
}
