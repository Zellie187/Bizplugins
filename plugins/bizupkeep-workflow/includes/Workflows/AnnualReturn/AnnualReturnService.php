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
        AnnualReturnDefinition::ACTION_REVISE_QUOTE,
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
     * Start an Annual Return workflow for an existing company, covering
     * one or more outstanding financial years in a single application -
     * a client behind on several years' filings pays for all of them
     * together, not as separate applications.
     *
     * @param array<int,array<string,mixed>> $filings  At least one {financial_year, turnover}
     *                                                  pair. Turnover matters because CIPC's filing
     *                                                  fee is turnover-banded. Untyped/unvalidated -
     *                                                  the theme is responsible for parsing/sanitizing
     *                                                  its own posted form data before calling this.
     * @param array<string,mixed>            $metadata Optional extra workflow metadata, e.g.
     *                                                  'client_notes' (free text the client entered
     *                                                  at submission, since nothing else transitions -
     *                                                  and so records a reason - until staff send a
     *                                                  quote).
     *
     * @throws ValidationException                                 If $filings is empty, or any requested
     *                                                               financial year already has a non-cancelled
     *                                                               Annual Return on file for this company.
     * @throws \BizHub\Companies\Exceptions\CompanyNotFoundException If the company does not exist.
     */
    public function start(string $companyUuid, int $userId, array $filings, array $metadata = []): WorkflowInstance
    {
        if ($filings === []) {
            throw new ValidationException(
                'At least one financial year is required to file an Annual Return.',
                ['filings' => 'Required.']
            );
        }

        $company = $this->companyService->getCompany($companyUuid);

        $duplicateYears = $this->alreadyFiledYears($company->getUuid(), $filings);

        if ($duplicateYears !== []) {
            throw new ValidationException(
                sprintf(
                    'An Annual Return has already been filed for this company for financial year(s): %s.',
                    implode(', ', $duplicateYears)
                ),
                ['filings' => 'Already filed.']
            );
        }

        return $this->workflowEngine->create(new CreateWorkflowCommand(
            AnnualReturnDefinition::TYPE,
            'company',
            $company->getUuid(),
            $userId,
            array_merge(['filings' => $filings], $metadata)
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
     * Return whichever of the requested financial years already has a
     * non-cancelled Annual Return on file for this company, across
     * every one of the company's existing Annual Return workflows
     * (each of which may itself cover several years).
     *
     * @param array<int,array<string,mixed>> $filings
     *
     * @return array<int,int>
     */
    private function alreadyFiledYears(string $companyUuid, array $filings): array
    {
        $requestedYears = array_map(
            static fn (array $filing): int => (int) ($filing['financial_year'] ?? 0),
            $filings
        );

        $existingYears = [];

        foreach ($this->workflowRepository->findForSubject('company', $companyUuid) as $workflow) {
            if (
                $workflow->getWorkflowType() !== AnnualReturnDefinition::TYPE
                || $workflow->getStatus() === WorkflowStatus::Cancelled
            ) {
                continue;
            }

            foreach (self::filingsFromMetadata($workflow->getMetadata()) as $filing) {
                $existingYears[] = (int) ($filing['financial_year'] ?? 0);
            }
        }

        return array_values(array_unique(array_intersect($requestedYears, $existingYears)));
    }

    /**
     * Normalize a workflow's metadata into a list of {financial_year,
     * turnover} filings, tolerating the old shape (a single flat
     * `financial_year` int, from before an application could cover
     * multiple years) so historical workflows are still counted
     * correctly by alreadyFiledYears().
     *
     * @param array<string,mixed> $metadata
     *
     * @return array<int,array<string,mixed>>
     */
    private static function filingsFromMetadata(array $metadata): array
    {
        if (isset($metadata['filings']) && is_array($metadata['filings'])) {
            return $metadata['filings'];
        }

        if (isset($metadata['financial_year'])) {
            return [['financial_year' => (int) $metadata['financial_year'], 'turnover' => null]];
        }

        return [];
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
