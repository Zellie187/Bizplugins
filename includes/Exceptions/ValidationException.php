<?php

declare(strict_types=1);

namespace BizHub\Workflow\Exceptions;

/**
 * Thrown when input data fails validation before it reaches the
 * workflow engine.
 *
 * @package BizHub\Workflow\Exceptions
 */
class ValidationException extends WorkflowException
{
    /**
     * @param array<string,string> $errors Field name => error message.
     */
    public function __construct(
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
