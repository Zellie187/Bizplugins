<?php

declare(strict_types=1);

namespace BizHub\Framework\Queue;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Support\Uuid;

/**
 * Database-backed job queue.
 *
 * @package BizHub\Framework\Queue
 */
final class Queue
{
    private const TABLE = 'bizhub_queue_jobs';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * Push a job onto the queue.
     */
    public function push(Job $job): string
    {
        $uuid = Uuid::generate();

        $this->database->insert(self::TABLE, [
            'uuid' => $uuid,
            'job_class' => $job::class,
            'payload' => json_encode($job->toPayload(), JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $uuid;
    }

    /**
     * Retrieve every pending job, oldest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function pending(): array
    {
        return $this->database->findAll(
            self::TABLE,
            ['status' => 'pending'],
            ['created_at' => 'ASC']
        );
    }

    /**
     * Mark a job as processed.
     */
    public function markProcessed(string $uuid): void
    {
        $this->database->update(self::TABLE, ['status' => 'processed'], ['uuid' => $uuid]);
    }

    /**
     * Mark a job as failed, recording the attempt.
     */
    public function markFailed(string $uuid, string $error): void
    {
        $this->database->update(self::TABLE, [
            'status' => 'failed',
            'last_error' => $error,
        ], ['uuid' => $uuid]);
    }

    /**
     * Increment a job's attempt counter.
     */
    public function incrementAttempts(string $uuid, int $currentAttempts): void
    {
        $this->database->update(self::TABLE, ['attempts' => $currentAttempts + 1], ['uuid' => $uuid]);
    }
}
