<?php

declare(strict_types=1);

namespace BizHub\Workflow\Repositories;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\DTO\Transition;
use BizHub\Workflow\DTO\WorkflowSummary;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use DateTimeImmutable;

/**
 * Persists workflow instances and their transition audit trail using
 * the BizHub Framework database abstraction (never $wpdb directly),
 * satisfying BH-WORKFLOW-SPEC-001 section 4's "database agnostic
 * through the BizHub database abstraction" requirement.
 *
 * @package BizHub\Workflow\Repositories
 */
final class WorkflowRepository implements WorkflowRepositoryInterface
{
    private const INSTANCES_TABLE = 'bizhub_workflow_instances';

    private const TRANSITIONS_TABLE = 'bizhub_workflow_transitions';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $uuid): ?WorkflowInstance
    {
        $row = $this->database->findOne(self::INSTANCES_TABLE, ['uuid' => $uuid]);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row)->withHistory($this->history($uuid));
    }

    /**
     * {@inheritDoc}
     */
    public function findForSubject(string $subjectType, string $subjectUuid): array
    {
        $rows = $this->database->findAll(
            self::INSTANCES_TABLE,
            ['subject_type' => $subjectType, 'subject_uuid' => $subjectUuid],
            ['created_at' => 'DESC']
        );

        return array_map(
            fn (array $row): WorkflowInstance =>
                $this->hydrate($row)->withHistory($this->history($row['uuid'])),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function summaries(string $workflowType, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->database->findAll(
            self::INSTANCES_TABLE,
            ['workflow_type' => $workflowType],
            ['updated_at' => 'DESC'],
            $limit,
            $offset
        );

        return array_map(
            fn (array $row): WorkflowSummary => new WorkflowSummary(
                $row['uuid'],
                $row['workflow_type'],
                $row['subject_type'],
                $row['subject_uuid'],
                WorkflowStatus::from($row['status']),
                $this->toDate($row['created_at']) ?? new DateTimeImmutable(),
                $this->toDate($row['updated_at'] ?? null)
            ),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function summariesByStatus(string $workflowType, WorkflowStatus $status, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->database->findAll(
            self::INSTANCES_TABLE,
            ['workflow_type' => $workflowType, 'status' => $status->value],
            ['updated_at' => 'DESC'],
            $limit,
            $offset
        );

        return array_map(
            fn (array $row): WorkflowSummary => new WorkflowSummary(
                $row['uuid'],
                $row['workflow_type'],
                $row['subject_type'],
                $row['subject_uuid'],
                WorkflowStatus::from($row['status']),
                $this->toDate($row['created_at']) ?? new DateTimeImmutable(),
                $this->toDate($row['updated_at'] ?? null)
            ),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save(WorkflowInstance $workflow): WorkflowInstance
    {
        $data = $this->dehydrate($workflow);

        if ($this->database->exists(self::INSTANCES_TABLE, ['uuid' => $workflow->getUuid()])) {
            $this->database->update(self::INSTANCES_TABLE, $data, ['uuid' => $workflow->getUuid()]);
        } else {
            $this->database->insert(self::INSTANCES_TABLE, $data);
        }

        return $workflow;
    }

    /**
     * {@inheritDoc}
     */
    public function recordTransition(Transition $transition): void
    {
        $this->database->insert(self::TRANSITIONS_TABLE, [
            'uuid' => $transition->uuid,
            'workflow_uuid' => $transition->workflowUuid,
            'from_status' => $transition->from?->value,
            'to_status' => $transition->to->value,
            'action' => $transition->action,
            'actor_id' => $transition->actorId,
            'reason' => $transition->reason,
            'context' => json_encode($transition->context, JSON_UNESCAPED_SLASHES),
            'occurred_at' => $transition->occurredAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function history(string $workflowUuid): array
    {
        $rows = $this->database->findAll(
            self::TRANSITIONS_TABLE,
            ['workflow_uuid' => $workflowUuid],
            ['occurred_at' => 'ASC']
        );

        return array_map(
            fn (array $row): Transition => new Transition(
                $row['uuid'],
                $row['workflow_uuid'],
                isset($row['from_status']) && $row['from_status'] !== null
                    ? WorkflowStatus::from($row['from_status'])
                    : null,
                WorkflowStatus::from($row['to_status']),
                $row['action'],
                (int) $row['actor_id'],
                $row['reason'] ?? '',
                json_decode((string) ($row['context'] ?? '[]'), true) ?? [],
                $this->toDate($row['occurred_at']) ?? new DateTimeImmutable()
            ),
            $rows
        );
    }

    /**
     * Hydrate a database row into a WorkflowInstance, without its
     * transition history (callers attach history separately, since it
     * is not always needed).
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): WorkflowInstance
    {
        return WorkflowInstance::hydrate(
            $row['uuid'],
            $row['workflow_type'],
            $row['subject_type'],
            $row['subject_uuid'],
            WorkflowStatus::from($row['status']),
            json_decode((string) ($row['metadata'] ?? '[]'), true) ?? [],
            (int) $row['created_by'],
            $this->toDate($row['created_at']) ?? new DateTimeImmutable(),
            $this->toDate($row['updated_at'] ?? null),
            $this->toDate($row['completed_at'] ?? null)
        );
    }

    /**
     * Convert a WorkflowInstance into a database row.
     *
     * @return array<string,mixed>
     */
    private function dehydrate(WorkflowInstance $workflow): array
    {
        return [
            'uuid' => $workflow->getUuid(),
            'workflow_type' => $workflow->getWorkflowType(),
            'subject_type' => $workflow->getSubjectType(),
            'subject_uuid' => $workflow->getSubjectUuid(),
            'status' => $workflow->getStatus()->value,
            'metadata' => json_encode($workflow->getMetadata(), JSON_UNESCAPED_SLASHES),
            'created_by' => $workflow->getCreatedBy(),
            'created_at' => $workflow->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $workflow->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'completed_at' => $workflow->getCompletedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate a new UUID for a transition row.
     */
    public static function newTransitionUuid(): string
    {
        return Uuid::generate();
    }

    /**
     * Parse a nullable date column into a DateTimeImmutable.
     */
    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }
}
