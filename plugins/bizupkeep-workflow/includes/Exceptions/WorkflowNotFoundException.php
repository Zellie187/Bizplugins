<?php

declare(strict_types=1);

namespace BizHub\Workflow\Exceptions;

/**
 * Thrown when a workflow instance cannot be found by its identifier.
 *
 * @package BizHub\Workflow\Exceptions
 */
class WorkflowNotFoundException extends WorkflowException
{
    public static function forUuid(string $uuid): self
    {
        return new self(
            sprintf('Workflow instance "%s" was not found.', $uuid)
        );
    }
}
