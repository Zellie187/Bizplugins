<?php

declare(strict_types=1);

namespace BizHub\Workflow\Exceptions;

/**
 * Thrown by a TransitionGuardInterface implementation when a
 * transition is structurally valid (declared by the workflow
 * definition) but a business-rule precondition for it has not been
 * met, e.g. attempting to verify documents before any have been
 * uploaded.
 *
 * @package BizHub\Workflow\Exceptions
 */
class PreconditionFailedException extends WorkflowException
{
}
