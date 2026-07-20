<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\CompanyAmendment;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Workflow\Contracts\WorkflowEngineInterface;
use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\DTO\RollbackWorkflowCommand;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;

/**
 * Service layer for the Company Amendment workflow, following the same
 * Controller -> Service -> Workflow Engine layering as
 * CompanyRegistrationService.
 *
 * @package BizHub\Workflow\Workflows\CompanyAmendment
 */
final class CompanyAmendmentService
{
    /**
     * @var array<int,string>
     */
    private const ALLOWED_ACTIONS = [
        CompanyAmendmentDefinition::ACTION_REQUEST_DOCUMENTS,
        CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS,
        CompanyAmendmentDefinition::ACTION_REQUEST_PAYMENT,
        CompanyAmendmentDefinition::ACTION_CONFIRM_PAYMENT,
        CompanyAmendmentDefinition::ACTION_START_QUALITY_REVIEW,
        CompanyAmendmentDefinition::ACTION_APPROVE,
        CompanyAmendmentDefinition::ACTION_ARCHIVE,
        CompanyAmendmentDefinition::ACTION_CANCEL,
        CompanyAmendmentDefinition::ACTION_REJECT,
    ];

    public function __construct(
        private readonly WorkflowEngineInterface $workflowEngine,
        private readonly CompanyServiceInterface $companyService,
    ) {
    }

    /**
     * Start a Company Amendment workflow for an existing company.
     *
     * @param array<int,string>              $amendmentTypes  One or more of
     *                                                         CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES.
     * @param array<int,string>              $proposedNames   Up to 4 names, in order of
     *                                                         preference. Required if 'name' is included.
     * @param array<int,array<string,mixed>> $directorChanges Add/remove/update entries.
     *                                                         Required if 'director' is included.
     * @param array<string,string>           $newAddress      Required if 'address' is included.
     *
     * @throws ValidationException                              If no valid amendment type is selected.
     * @throws \BizHub\Companies\Exceptions\CompanyNotFoundException If the company does not exist.
     */
    public function start(
        string $companyUuid,
        int $userId,
        array $amendmentTypes,
        array $proposedNames = [],
        array $directorChanges = [],
        array $newAddress = []
    ): WorkflowInstance {
        $company = $this->companyService->getCompany($companyUuid);

        $amendmentTypes = array_values(array_unique($amendmentTypes));
        $unknownTypes = array_diff($amendmentTypes, CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES);

        if ($amendmentTypes === [] || $unknownTypes !== []) {
            throw new ValidationException(
                'At least one valid amendment type must be selected.',
                ['amendment_types' => 'Must be one or more of: director, name, address.']
            );
        }

        return $this->workflowEngine->create(new CreateWorkflowCommand(
            CompanyAmendmentDefinition::TYPE,
            'company',
            $company->getUuid(),
            $userId,
            [
                'amendment_types' => $amendmentTypes,
                'proposed_names' => $proposedNames,
                'director_changes' => $directorChanges,
                'new_address' => $newAddress,
            ]
        ));
    }

    /**
     * Perform a named action against a Company Amendment workflow.
     *
     * @param array<string,mixed> $context
     *
     * @throws ValidationException      If $action is not a Company Amendment action.
     * @throws WorkflowNotFoundException If the workflow does not exist or is not a
     *                                    Company Amendment.
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
                sprintf('"%s" is not a valid Company Amendment action.', $action),
                ['action' => 'Unknown action.']
            );
        }

        $this->assertIsCompanyAmendment($this->workflowEngine->find($workflowUuid));

        return $this->workflowEngine->transition(
            new TransitionWorkflowCommand($workflowUuid, $action, $userId, $reason, $context)
        );
    }

    /**
     * Roll a Company Amendment workflow back to its previous status.
     */
    public function rollback(string $workflowUuid, int $userId, string $reason): WorkflowInstance
    {
        $this->assertIsCompanyAmendment($this->workflowEngine->find($workflowUuid));

        return $this->workflowEngine->rollback(
            new RollbackWorkflowCommand($workflowUuid, $userId, $reason)
        );
    }

    /**
     * Retrieve a Company Amendment workflow instance.
     *
     * @throws WorkflowNotFoundException
     */
    public function find(string $workflowUuid): WorkflowInstance
    {
        $workflow = $this->workflowEngine->find($workflowUuid);

        $this->assertIsCompanyAmendment($workflow);

        return $workflow;
    }

    /**
     * Retrieve a Company Amendment workflow's full transition history.
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
     * is of a different workflow type.
     */
    private function assertIsCompanyAmendment(WorkflowInstance $workflow): void
    {
        if ($workflow->getWorkflowType() !== CompanyAmendmentDefinition::TYPE) {
            throw WorkflowNotFoundException::forUuid($workflow->getUuid());
        }
    }
}
