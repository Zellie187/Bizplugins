<?php

declare(strict_types=1);

namespace BizHub\Workflow\Contracts;

/**
 * Marker interface for Command-pattern request objects (e.g.
 * CreateWorkflowCommand, TransitionWorkflowCommand). Encapsulating a
 * request as an object lets WorkflowManager expose a small, stable
 * public API regardless of how many parameters a given operation
 * needs internally.
 *
 * @package BizHub\Workflow\Contracts
 */
interface CommandInterface
{
}
