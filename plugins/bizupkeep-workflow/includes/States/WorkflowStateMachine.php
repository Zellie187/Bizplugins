<?php

declare(strict_types=1);

namespace BizHub\Workflow\States;

use BizHub\Workflow\Contracts\WorkflowDefinitionInterface;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\InvalidTransitionException;

/**
 * Resolves the target status for a named action against a workflow
 * definition, rejecting any action that definition does not declare
 * or does not permit from the workflow's current status.
 *
 * This is the sole authority for "is this transition allowed" -
 * WorkflowManager never inspects a definition's rules directly, so
 * every workflow type is guaranteed to be enforced the same way.
 *
 * @package BizHub\Workflow\States
 */
final class WorkflowStateMachine
{
    /**
     * Resolve the status a workflow moves to after performing $action
     * from $current, according to $definition.
     *
     * @throws InvalidTransitionException If the action is not declared by the
     *                                     definition, or is not permitted from $current.
     */
    public function apply(
        WorkflowDefinitionInterface $definition,
        WorkflowStatus $current,
        string $action
    ): WorkflowStatus {
        if ($current->isTerminal()) {
            throw new InvalidTransitionException(sprintf(
                'Cannot perform action "%s": workflow is already in a terminal status ("%s").',
                $action,
                $current->value
            ));
        }

        $rule = $definition->transitionRules()[$action] ?? null;

        if ($rule === null) {
            throw new InvalidTransitionException(sprintf(
                'Workflow type "%s" has no action named "%s".',
                $definition->workflowType(),
                $action
            ));
        }

        if (! $rule->allowsFrom($current)) {
            throw new InvalidTransitionException(sprintf(
                'Action "%s" cannot be performed while workflow "%s" is in status "%s".',
                $action,
                $definition->workflowType(),
                $current->value
            ));
        }

        return $rule->to;
    }

    /**
     * Return the action names permitted from a workflow's current
     * status, e.g. to drive which buttons a UI shows.
     *
     * @return array<int,string>
     */
    public function allowedActions(WorkflowDefinitionInterface $definition, WorkflowStatus $current): array
    {
        if ($current->isTerminal()) {
            return [];
        }

        $actions = [];

        foreach ($definition->transitionRules() as $action => $rule) {
            if ($rule->allowsFrom($current)) {
                $actions[] = $action;
            }
        }

        return $actions;
    }
}
