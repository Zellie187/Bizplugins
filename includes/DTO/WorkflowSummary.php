<?php

declare(strict_types=1);

namespace BizHub\Workflow\DTO;

use BizHub\Workflow\Enums\WorkflowStatus;
use DateTimeImmutable;

/**
 * A lightweight projection of a workflow instance, suitable for list
 * views (admin screens, client portal, REST index endpoints) without
 * loading its full transition history.
 *
 * @package BizHub\Workflow\DTO
 */
final readonly class WorkflowSummary
{
    public function __construct(
        public string $uuid,
        public string $workflowType,
        public string $subjectType,
        public string $subjectUuid,
        public WorkflowStatus $status,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }
}
