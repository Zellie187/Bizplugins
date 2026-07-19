<?php

declare(strict_types=1);

namespace BizHub\Workflow\Exceptions;

/**
 * Thrown when the current user is not permitted to perform a
 * workflow operation.
 *
 * @package BizHub\Workflow\Exceptions
 */
class AuthorizationException extends WorkflowException
{
}
