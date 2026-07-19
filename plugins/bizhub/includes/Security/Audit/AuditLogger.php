<?php

declare(strict_types=1);

namespace BizHub\Security\Audit;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Support\Uuid;

/**
 * Persists audit trail entries.
 *
 * Assumes a table with (uuid, action, subject_type, subject_id, user_id,
 * context, occurred_at) columns. Table creation is the responsibility of
 * the framework's install layer.
 *
 * @package BizHub\Security\Audit
 */
final class AuditLogger
{
    private const TABLE = 'bizhub_audit_log';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * Record an audit event.
     */
    public function record(AuditEvent $event): void
    {
        $this->database->insert(self::TABLE, $event->toArray());
    }

    /**
     * Build and record an audit event in a single call.
     *
     * @param array<string,mixed> $context
     */
    public function log(
        string $action,
        string $subjectType,
        string $subjectId,
        array $context = [],
        ?int $userId = null
    ): void {
        $this->record(new AuditEvent(
            Uuid::generate(),
            $action,
            $subjectType,
            $subjectId,
            $userId,
            $context
        ));
    }

    /**
     * Retrieve the audit trail for a specific subject.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forSubject(string $subjectType, string $subjectId): array
    {
        return $this->database->findAll(
            self::TABLE,
            ['subject_type' => $subjectType, 'subject_id' => $subjectId],
            ['occurred_at' => 'DESC']
        );
    }
}
