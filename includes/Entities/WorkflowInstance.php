<?php

declare(strict_types=1);

namespace BizHub\Workflow\Entities;

use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\Enums\WorkflowStatus;
use DateTimeImmutable;

/**
 * A running (or concluded) instance of a workflow definition, bound to
 * a single business subject (e.g. a company registration bound to a
 * BizHub\Companies\Entities\Company).
 *
 * This is the workflow engine's aggregate root: all state changes to
 * a workflow instance happen through applyTransition(), which is the
 * only way its status, updatedAt and in-memory history can change.
 *
 * @package BizHub\Workflow\Entities
 */
final class WorkflowInstance
{
    /**
     * @var array<int,Transition>
     */
    private array $history = [];

    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        private readonly string $uuid,
        private readonly string $workflowType,
        private readonly string $subjectType,
        private readonly string $subjectUuid,
        private WorkflowStatus $status,
        private array $metadata,
        private readonly int $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $completedAt,
    ) {
    }

    /**
     * Start a brand-new workflow instance in its definition's initial
     * status.
     *
     * @param array<string,mixed> $metadata
     */
    public static function start(
        string $uuid,
        string $workflowType,
        string $subjectType,
        string $subjectUuid,
        WorkflowStatus $initialStatus,
        int $createdBy,
        array $metadata = []
    ): self {
        return new self(
            $uuid,
            $workflowType,
            $subjectType,
            $subjectUuid,
            $initialStatus,
            $metadata,
            $createdBy,
            new DateTimeImmutable(),
            null,
            null
        );
    }

    /**
     * Reconstruct a workflow instance from previously persisted data.
     * Used only by WorkflowRepository; use start() to create a new
     * instance instead.
     *
     * @param array<string,mixed> $metadata
     */
    public static function hydrate(
        string $uuid,
        string $workflowType,
        string $subjectType,
        string $subjectUuid,
        WorkflowStatus $status,
        array $metadata,
        int $createdBy,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $completedAt
    ): self {
        return new self(
            $uuid,
            $workflowType,
            $subjectType,
            $subjectUuid,
            $status,
            $metadata,
            $createdBy,
            $createdAt,
            $updatedAt,
            $completedAt
        );
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectUuid(): string
    {
        return $this->subjectUuid;
    }

    public function getStatus(): WorkflowStatus
    {
        return $this->status;
    }

    /**
     * @return array<string,mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Restore previously persisted transition history. Used only by
     * the repository when hydrating a workflow instance; does not
     * change status.
     *
     * @param array<int,Transition> $history
     */
    public function withHistory(array $history): self
    {
        $this->history = $history;

        return $this;
    }

    /**
     * @return array<int,Transition>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Apply a transition already validated by the state machine and
     * any registered guard. This is the only way a workflow instance's
     * status may change.
     */
    public function applyTransition(Transition $transition): void
    {
        $this->status = $transition->to;
        $this->updatedAt = $transition->occurredAt;
        $this->history[] = $transition;

        /*
         * Recorded the first time a workflow reaches a successful
         * status (e.g. Completed), not tied to isTerminal(): Completed
         * is deliberately not terminal in this engine (it can still
         * move to Archived), but that later housekeeping step should
         * not push completedAt forward in time.
         */
        if ($transition->to->isSuccessful() && $this->completedAt === null) {
            $this->completedAt = $transition->occurredAt;
        }
    }

    /**
     * Merge additional metadata into the workflow instance, e.g. to
     * record that documents were uploaded or a payment reference was
     * captured.
     *
     * @param array<string,mixed> $metadata
     */
    public function mergeMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
    }
}
