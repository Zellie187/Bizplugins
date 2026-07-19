<?php

declare(strict_types=1);

namespace BizHub\Security\Audit;

use DateTimeImmutable;

/**
 * Represents a single entry in the audit trail.
 *
 * @package BizHub\Security\Audit
 */
final readonly class AuditEvent
{
    /**
     * @param string             $uuid
     * @param string             $action      Dot-notated action name, e.g. "company.created".
     * @param string             $subjectType Class or entity type the action was performed on.
     * @param string             $subjectId   Identifier of the subject.
     * @param int|null           $userId      WordPress user ID responsible, or null for system actions.
     * @param array<string,mixed> $context    Additional contextual data.
     * @param DateTimeImmutable  $occurredAt
     */
    public function __construct(
        public string $uuid,
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public ?int $userId = null,
        public array $context = [],
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
    ) {
    }

    /**
     * Export the event as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'action' => $this->action,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'user_id' => $this->userId,
            'context' => $this->context === [] ? null : json_encode($this->context, JSON_UNESCAPED_SLASHES),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
