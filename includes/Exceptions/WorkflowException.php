<?php

declare(strict_types=1);

namespace BizHub\Workflow\Exceptions;

use Exception;

/**
 * Base exception for BizUpKeep Workflow.
 *
 * All workflow-specific exceptions extend this class so calling code
 * can catch a single type and translate it into a safe, user-facing
 * response rather than exposing raw exception detail (BH-WORKFLOW-
 * SPEC-001 section 11).
 *
 * @package BizHub\Workflow\Exceptions
 */
class WorkflowException extends Exception
{
}
