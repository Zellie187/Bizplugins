<?php

declare(strict_types=1);

namespace BizHub\Workflow\DTO;

use BizHub\Workflow\Contracts\CommandInterface;

/**
 * Command: perform a named action against an existing workflow
 * instance, moving it to whatever status its definition maps that
 * action to from its current status.
 *
 * @package BizHub\Workflow\DTO
 */
final readonly class TransitionWorkflowCommand implements CommandInterface
{
    /**
     * @param array<string,mixed> $context Action-specific data, e.g. an uploaded document's UUID.
     */
    public function __construct(
        public string $workflowUuid,
        public string $action,
        public int $actorId,
        public string $reason = '',
        public array $context = [],
    ) {
    }
}
