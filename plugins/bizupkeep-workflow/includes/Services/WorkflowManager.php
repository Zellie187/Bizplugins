<?php

declare(strict_types=1);

namespace BizHub\Workflow\Services;

use BizHub\Framework\Events\EventDispatcher;
use BizHub\Framework\Logging\Logger;
use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\Contracts\TransitionGuardInterface;
use BizHub\Workflow\Contracts\WorkflowDefinitionInterface;
use BizHub\Workflow\Contracts\WorkflowEngineInterface;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\DTO\CreateWorkflowCommand;
use BizHub\Workflow\DTO\ForceStatusCommand;
use BizHub\Workflow\DTO\RollbackWorkflowCommand;
use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\DTO\TransitionWorkflowCommand;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Events\WorkflowCancelled;
use BizHub\Workflow\Events\WorkflowCompleted;
use BizHub\Workflow\Events\WorkflowCreated;
use BizHub\Workflow\Events\WorkflowRolledBack;
use BizHub\Workflow\Events\WorkflowTransitioned;
use BizHub\Workflow\Exceptions\InvalidTransitionException;
use BizHub\Workflow\Exceptions\WorkflowNotFoundException;
use BizHub\Workflow\States\WorkflowStateMachine;
use DateTimeImmutable;

/**
 * The workflow engine's Service layer / Facade: the single place
 * every concrete workflow's Controller talks to, per BH-WORKFLOW-
 * SPEC-001 section 4 (Controller -> Service -> Workflow Engine ->
 * Repository -> Framework Database).
 *
 * Concrete workflow types register their WorkflowDefinitionInterface
 * (State pattern) and, optionally, a TransitionGuardInterface
 * (Strategy pattern) here during their Service Provider's boot(). This
 * class then enforces every workflow's lifecycle uniformly: structural
 * transition validity via WorkflowStateMachine, business-rule
 * preconditions via the registered guard, an event raised for every
 * state change (Observer / Event Dispatcher pattern), and a structured
 * audit log entry for every action.
 *
 * @package BizHub\Workflow\Services
 */
final class WorkflowManager implements WorkflowEngineInterface
{
    /**
     * The action name recorded against a transition produced by
     * forceStatus() - distinct from every action name a
     * WorkflowDefinitionInterface declares, so it is unambiguous in a
     * workflow's history that a step did not come from its normal
     * guarded lifecycle.
     */
    public const ACTION_ADMIN_OVERRIDE = 'admin_override';

    /**
     * @var array<string,WorkflowDefinitionInterface>
     */
    private array $definitions = [];

    /**
     * @var array<string,TransitionGuardInterface>
     */
    private array $guards = [];

    public function __construct(
        private readonly WorkflowRepositoryInterface $repository,
        private readonly WorkflowStateMachine $stateMachine,
        private readonly EventDispatcher $events,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Register a workflow type's definition and, optionally, its
     * business-rule guard. Called once per workflow type, from that
     * workflow's Service Provider.
     */
    public function registerDefinition(
        WorkflowDefinitionInterface $definition,
        ?TransitionGuardInterface $guard = null
    ): void {
        $this->definitions[$definition->workflowType()] = $definition;

        if ($guard !== null) {
            $this->guards[$definition->workflowType()] = $guard;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateWorkflowCommand $command): WorkflowInstance
    {
        $definition = $this->definitionFor($command->workflowType);

        $workflow = WorkflowInstance::start(
            Uuid::generate(),
            $command->workflowType,
            $command->subjectType,
            $command->subjectUuid,
            $definition->initialStatus(),
            $command->createdBy,
            $command->metadata
        );

        $this->repository->save($workflow);

        $this->logger->info('bizupkeep_workflow.created', [
            'workflow_uuid' => $workflow->getUuid(),
            'workflow_type' => $workflow->getWorkflowType(),
            'subject_type' => $workflow->getSubjectType(),
            'subject_uuid' => $workflow->getSubjectUuid(),
            'status' => $workflow->getStatus()->value,
            'user_id' => $command->createdBy,
        ]);

        $this->events->dispatch(new WorkflowCreated($workflow));

        return $workflow;
    }

    /**
     * {@inheritDoc}
     */
    public function transition(TransitionWorkflowCommand $command): WorkflowInstance
    {
        $workflow = $this->findOrFail($command->workflowUuid);
        $definition = $this->definitionFor($workflow->getWorkflowType());

        $to = $this->stateMachine->apply($definition, $workflow->getStatus(), $command->action);

        $guard = $this->guards[$workflow->getWorkflowType()] ?? null;

        $guard?->guard($workflow, $to, $command->action, $command->context);

        $transition = new Transition(
            Uuid::generate(),
            $workflow->getUuid(),
            $workflow->getStatus(),
            $to,
            $command->action,
            $command->actorId,
            $command->reason,
            $command->context,
            new DateTimeImmutable()
        );

        $workflow->applyTransition($transition);

        if ($command->context !== []) {
            $workflow->mergeMetadata($command->context);
        }

        $this->repository->save($workflow);
        $this->repository->recordTransition($transition);

        $this->logger->info('bizupkeep_workflow.transitioned', [
            'workflow_uuid' => $workflow->getUuid(),
            'workflow_type' => $workflow->getWorkflowType(),
            'action' => $command->action,
            'from_status' => $transition->from?->value,
            'to_status' => $transition->to->value,
            'user_id' => $command->actorId,
            'reason' => $command->reason,
        ]);

        $this->events->dispatch(new WorkflowTransitioned($workflow, $transition));

        /*
         * WorkflowCompleted fires the moment a workflow reaches its
         * successful conclusion (WorkflowStatus::Completed), not when
         * it is later archived - Archived is a housekeeping status
         * reached afterwards, not a second "completion".
         */
        if ($to === WorkflowStatus::Completed) {
            $this->events->dispatch(new WorkflowCompleted($workflow));
        }

        if ($to->isTerminal() && ! $to->isSuccessful()) {
            $this->events->dispatch(new WorkflowCancelled($workflow, $command->reason));
        }

        return $workflow;
    }

    /**
     * {@inheritDoc}
     */
    public function rollback(RollbackWorkflowCommand $command): WorkflowInstance
    {
        $workflow = $this->findOrFail($command->workflowUuid);

        if ($workflow->isTerminal()) {
            throw new InvalidTransitionException(sprintf(
                'Cannot roll back workflow "%s": it has already reached a terminal status ("%s").',
                $workflow->getUuid(),
                $workflow->getStatus()->value
            ));
        }

        $history = $workflow->getHistory();
        $lastTransition = $history[count($history) - 1] ?? null;

        if ($lastTransition === null || $lastTransition->from === null) {
            throw new InvalidTransitionException(sprintf(
                'Workflow "%s" has no prior status to roll back to.',
                $workflow->getUuid()
            ));
        }

        $rollbackTransition = new Transition(
            Uuid::generate(),
            $workflow->getUuid(),
            $workflow->getStatus(),
            $lastTransition->from,
            'rollback',
            $command->actorId,
            $command->reason,
            ['rolled_back_transition' => $lastTransition->uuid],
            new DateTimeImmutable()
        );

        $workflow->applyTransition($rollbackTransition);

        $this->repository->save($workflow);
        $this->repository->recordTransition($rollbackTransition);

        $this->logger->warning('bizupkeep_workflow.rolled_back', [
            'workflow_uuid' => $workflow->getUuid(),
            'workflow_type' => $workflow->getWorkflowType(),
            'to_status' => $rollbackTransition->to->value,
            'user_id' => $command->actorId,
            'reason' => $command->reason,
        ]);

        $this->events->dispatch(new WorkflowRolledBack($workflow, $command->reason));

        return $workflow;
    }

    /**
     * {@inheritDoc}
     */
    public function forceStatus(ForceStatusCommand $command): WorkflowInstance
    {
        $workflow = $this->findOrFail($command->workflowUuid);

        if ($workflow->isTerminal()) {
            throw new InvalidTransitionException(sprintf(
                'Cannot override workflow "%s": it has already reached a terminal status ("%s").',
                $workflow->getUuid(),
                $workflow->getStatus()->value
            ));
        }

        if ($workflow->getStatus() === $command->to) {
            throw new InvalidTransitionException(sprintf(
                'Workflow "%s" is already at status "%s".',
                $workflow->getUuid(),
                $command->to->value
            ));
        }

        $transition = new Transition(
            Uuid::generate(),
            $workflow->getUuid(),
            $workflow->getStatus(),
            $command->to,
            self::ACTION_ADMIN_OVERRIDE,
            $command->actorId,
            $command->reason,
            [],
            new DateTimeImmutable()
        );

        $workflow->applyTransition($transition);

        $this->repository->save($workflow);
        $this->repository->recordTransition($transition);

        $this->logger->warning('bizupkeep_workflow.admin_override', [
            'workflow_uuid' => $workflow->getUuid(),
            'workflow_type' => $workflow->getWorkflowType(),
            'from_status' => $transition->from?->value,
            'to_status' => $transition->to->value,
            'user_id' => $command->actorId,
            'reason' => $command->reason,
        ]);

        $this->events->dispatch(new WorkflowTransitioned($workflow, $transition));

        if ($command->to === WorkflowStatus::Completed) {
            $this->events->dispatch(new WorkflowCompleted($workflow));
        }

        if ($command->to->isTerminal() && ! $command->to->isSuccessful()) {
            $this->events->dispatch(new WorkflowCancelled($workflow, $command->reason));
        }

        return $workflow;
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $uuid): WorkflowInstance
    {
        return $this->findOrFail($uuid);
    }

    /**
     * {@inheritDoc}
     */
    public function historyFor(string $uuid): array
    {
        return $this->repository->history($uuid);
    }

    private function findOrFail(string $uuid): WorkflowInstance
    {
        return $this->repository->find($uuid) ?? throw WorkflowNotFoundException::forUuid($uuid);
    }

    private function definitionFor(string $workflowType): WorkflowDefinitionInterface
    {
        return $this->definitions[$workflowType]
            ?? throw new InvalidTransitionException(sprintf(
                'No workflow definition registered for type "%s".',
                $workflowType
            ));
    }
}
