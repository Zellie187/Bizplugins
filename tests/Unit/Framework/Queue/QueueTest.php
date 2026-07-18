<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Queue;

use BizHub\Framework\Logging\Logger;
use BizHub\Framework\Logging\LogManager;
use BizHub\Framework\Queue\Job;
use BizHub\Framework\Queue\Queue;
use BizHub\Framework\Queue\Worker;
use BizHub\Tests\Mocks\InMemoryDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RecordingJob implements Job
{
    public static bool $ran = false;

    public function __construct(
        private readonly string $message,
        private readonly bool $shouldFail = false
    ) {
    }

    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Simulated job failure');
        }

        self::$ran = true;
    }

    public function toPayload(): array
    {
        return ['message' => $this->message, 'shouldFail' => $this->shouldFail];
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload['message'], $payload['shouldFail']);
    }
}

final class QueueTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingJob::$ran = false;
    }

    public function test_push_and_process_job(): void
    {
        $db = new InMemoryDatabase();
        $queue = new Queue($db);
        $worker = new Worker($queue, new Logger(new LogManager()));

        $queue->push(new RecordingJob('hello'));
        $this->assertCount(1, $queue->pending());

        $processed = $worker->work();

        $this->assertSame(1, $processed);
        $this->assertTrue(RecordingJob::$ran);
        $this->assertCount(0, $queue->pending());
    }

    public function test_failing_job_is_retried_before_being_marked_failed(): void
    {
        $db = new InMemoryDatabase();
        $queue = new Queue($db);
        $worker = new Worker($queue, new Logger(new LogManager()));

        $uuid = $queue->push(new RecordingJob('will fail', true));
        $worker->work();

        $row = $db->findOne('bizhub_queue_jobs', ['uuid' => $uuid]);

        $this->assertSame(1, $row['attempts']);
        $this->assertSame('pending', $row['status']);
    }
}
