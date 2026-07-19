<?php

declare(strict_types=1);

namespace BizHub\Workflow\DTO;

use BizHub\Workflow\Contracts\CommandInterface;

/**
 * Command: start a new workflow instance of a given type, bound to a
 * business subject.
 *
 * @package BizHub\Workflow\DTO
 */
final readonly class CreateWorkflowCommand implements CommandInterface
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $workflowType,
        public string $subjectType,
        public string $subjectUuid,
        public int $createdBy,
        public array $metadata = [],
    ) {
    }
}
