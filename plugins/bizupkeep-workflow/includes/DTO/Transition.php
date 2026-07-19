<?php

declare(strict_types=1);

namespace BizHub\Workflow\DTO;

use BizHub\Workflow\Enums\WorkflowStatus;
use DateTimeImmutable;

/**
 * An immutable record of a single state transition applied to a
 * workflow instance. Persisted as an audit trail row so every state
 * change is traceable to an actor, a reason and a point in time.
 *
 * @package BizHub\Workflow\DTO
 */
final readonly class Transition
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(
        public string $uuid,
        public string $workflowUuid,
        public ?WorkflowStatus $from,
        public WorkflowStatus $to,
        public string $action,
        public int $actorId,
        public string $reason,
        public array $context,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
