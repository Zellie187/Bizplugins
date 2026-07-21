<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\AnnualReturn;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Workflow\Contracts\WorkflowEngineInterface;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\Contracts\WorkflowTypeServiceInterface;
use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\DTO\RollbackWorkflowCommand;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\ValidationException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;

/**
 * Service layer for the Annual Return workflow, following the same
 * Controller -> Service -> Workflow Engine layering as
 * CompanyRegistrationService.
 *
 * @package BizHub\Workflow\Workflows\AnnualReturn
 */
final class AnnualReturnService implements WorkflowTypeServiceInterface
{
    /**
     * @var array<int,string>
     */
    private const ALLOWED_ACTIONS = [
        AnnualReturnDefinition::ACTION_REQUEST_PAYMENT,
        AnnualReturnDefinition::ACTION_CONFIRM_PAYMENT,
        AnnualReturnDefinition::ACTION_START_QUALITY_REVIEW,
        AnnualReturnDefinition::ACTION_APPROVE,
        AnnualReturnDefinition::ACTION_ARCHIVE,
        AnnualReturnDefinition::ACTION_CANCEL,
    ];

    public function __construct(
        private readonly WorkflowEngineInterface $workflowEngine,
        private readonly WorkflowRepositoryInterface $workflowRepository,
        private readonly CompanyServiceInterface $companyService,
    ) {
    }

    /**
     * Start an Annual Return workflow for an existing company and
     * financial year.
     *
     * @throws ValidationException                              If that financial year already has a
     *                                                            non-cancelled Annual Return on file.
     * @throws \BizHub\Companies\Exceptions\CompanyNotFoundException If the company does not exist.
     */
    public function start(string $companyUuid, int $userId, int $financialYear): WorkflowInstance
    {
        $company = $this->companyService->getCompany($companyUuid);

        if ($this->alreadyFiled($company->getUuid(), $financialYear)) {
            throw new ValidationException(
                sprintf(
                    'An Annual Return for financial year %d has already been filed for this company.',
                    $financialYear
                ),
                ['financial_year' => 'Already filed.']
            );
        }

        return $this->workflowEngine->create(new CreateWorkflowCommand(
            AnnualReturnDefinition::TYPE,
            'company',
            $company->getUuid(),
            $userId,
            ['financial_year' => $financialYear]
        ));
    }

    /**
     * Perform a named action against an Annual Return workflow.
     *
     * @param array<string,mixed> $context
     *
     * @throws ValidationException      If $action is not an Annual Return action.
     * @throws WorkflowNotFoundException If the workflow does not exist or is not an
     *                                    Annual Return.
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
                sprintf('"%s" is not a valid Annual Return action.', $action),
                ['action' => 'Unknown action.']
            );
        }

        $this->assertIsAnnualReturn($this->workflowEngine->find($workflowUuid));

        return $this->workflowEngine->transition(
            new TransitionWorkflowCommand($workflowUuid, $action, $userId, $reason, $context)
        );
    }

    /**
     * Roll an Annual Return workflow back to its previous status.
     */
    public function rollback(string $workflowUuid, int $userId, string $reason): WorkflowInstance
    {
        $this->assertIsAnnualReturn($this->workflowEngine->find($workflowUuid));

        return $this->workflowEngine->rollback(
            new RollbackWorkflowCommand($workflowUuid, $userId, $reason)
        );
    }

    /**
     * Retrieve an Annual Return workflow instance.
     *
     * @throws WorkflowNotFoundException
     */
    public function find(string $workflowUuid): WorkflowInstance
    {
        $workflow = $this->workflowEngine->find($workflowUuid);

        $this->assertIsAnnualReturn($workflow);

        return $workflow;
    }

    /**
     * Retrieve an Annual Return workflow's full transition history.
     *
     * @return array<int,\BizHub\Workflow\DTO\Transition>
     */
    public function historyFor(string $workflowUuid): array
    {
        $this->find($workflowUuid);

        return $this->workflowEngine->historyFor($workflowUuid);
    }

    /**
     * Determine whether a non-cancelled Annual Return already exists
     * for this company and financial year.
     */
    private function alreadyFiled(string $companyUuid, int $financialYear): bool
    {
        foreach ($this->workflowRepository->findForSubject('company', $companyUuid) as $workflow) {
            if (
                $workflow->getWorkflowType() === AnnualReturnDefinition::TYPE
                && (int) ($workflow->getMetadata()['financial_year'] ?? 0) === $financialYear
                && $workflow->getStatus() !== WorkflowStatus::Cancelled
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guard against operating on a workflow instance that exists but
     * is of a different workflow type.
     */
    private function assertIsAnnualReturn(WorkflowInstance $workflow): void
    {
        if ($workflow->getWorkflowType() !== AnnualReturnDefinition::TYPE) {
            throw WorkflowNotFoundException::forUuid($workflow->getUuid());
        }
    }
}
