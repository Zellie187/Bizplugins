<?php

declare(strict_types=1);

namespace BizHub\Workflow\Exceptions;

/**
 * Thrown when a requested state transition is not permitted by a
 * workflow definition, i.e. an arbitrary/unmapped transition
 * (BH-WORKFLOW-SPEC-001 section 7: "No arbitrary state transitions
 * should be allowed").
 *
 * @package BizHub\Workflow\Exceptions
 */
class InvalidTransitionException extends WorkflowException
{
}
