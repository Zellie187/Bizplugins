<?php

declare(strict_types=1);

namespace BizHub\Framework\Queue;

use BizHub\Framework\Logging\Logger;
use Throwable;

/**
 * Processes pending jobs from the queue.
 *
 * @package BizHub\Framework\Queue
 */
final class Worker
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly Queue $queue,
        private readonly Logger $logger
    ) {
    }

    /**
     * Process every currently pending job.
     *
     * @return int Number of jobs processed successfully.
     */
    public function work(): int
    {
        $processed = 0;

        foreach ($this->queue->pending() as $row) {
            if ($this->processJob($row)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Process a single queued job row.
     *
     * @param array<string,mixed> $row
     */
    private function processJob(array $row): bool
    {
        $jobClass = $row['job_class'];

        if (! class_exists($jobClass) || ! is_subclass_of($jobClass, Job::class)) {
            $this->queue->markFailed($row['uuid'], sprintf('Job class "%s" is not a valid Job.', $jobClass));

            return false;
        }

        $payload = json_decode((string) $row['payload'], true) ?? [];

        try {
            /** @var Job $job */
            $job = $jobClass::fromPayload($payload);
            $job->handle();

            $this->queue->markProcessed($row['uuid']);

            return true;
        } catch (Throwable $e) {
            $attempts = (int) $row['attempts'];

            $this->queue->incrementAttempts($row['uuid'], $attempts);

            if ($attempts + 1 >= self::MAX_ATTEMPTS) {
                $this->queue->markFailed($row['uuid'], $e->getMessage());
            }

            $this->logger->error(
                sprintf('Queue job "%s" failed: %s', $jobClass, $e->getMessage()),
                ['uuid' => $row['uuid'], 'attempts' => $attempts + 1]
            );

            return false;
        }
    }
}
