<?php

declare(strict_types=1);

namespace BizHub\Framework\Queue;

/**
 * Contract for queueable jobs.
 *
 * Implementations must be re-creatable from a plain array payload so
 * that jobs can be persisted and later reconstructed by the Worker.
 *
 * @package BizHub\Framework\Queue
 */
interface Job
{
    /**
     * Execute the job.
     */
    public function handle(): void;

    /**
     * Export the job's constructor arguments as a plain array.
     *
     * @return array<string,mixed>
     */
    public function toPayload(): array;

    /**
     * Reconstruct the job from a previously exported payload.
     *
     * @param array<string,mixed> $payload
     */
    public static function fromPayload(array $payload): self;
}
