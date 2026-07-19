<?php

declare(strict_types=1);

namespace BizHub\Workflow\Contracts;

use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\DTO\WorkflowSummary;
use BizHub\Workflow\Entities\WorkflowInstance;

/**
 * Persists workflow instances and their transition history using the
 * BizHub Framework database abstraction.
 *
 * @package BizHub\Workflow\Contracts
 */
interface WorkflowRepositoryInterface
{
    /**
     * Find a workflow instance by its UUID, including its transition
     * history.
     */
    public function find(string $uuid): ?WorkflowInstance;

    /**
     * Find every workflow instance bound to a given business subject
     * (e.g. every workflow ever run against one company).
     *
     * @return array<int,WorkflowInstance>
     */
    public function findForSubject(string $subjectType, string $subjectUuid): array;

    /**
     * Return lightweight summaries of every workflow instance of a
     * given type, most recently updated first.
     *
     * @return array<int,WorkflowSummary>
     */
    public function summaries(string $workflowType, int $limit = 50, int $offset = 0): array;

    /**
     * Persist a workflow instance's current status/metadata snapshot.
     * Does not touch its transition history - see recordTransition().
     */
    public function save(WorkflowInstance $workflow): WorkflowInstance;

    /**
     * Append a single transition to a workflow instance's persisted
     * audit trail. Called once per transition, in addition to (not
     * instead of) save().
     */
    public function recordTransition(Transition $transition): void;

    /**
     * Return the full, ordered transition history for a workflow
     * instance.
     *
     * @return array<int,Transition>
     */
    public function history(string $workflowUuid): array;
}
