<?php

declare(strict_types=1);

namespace BizHub\Payments\Tests\Unit\Repositories;

use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Repositories\WebhookEventLogRepository;
use BizHub\Payments\Tests\Mocks\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class WebhookEventLogRepositoryTest extends TestCase
{
    private InMemoryDatabase $database;

    private WebhookEventLogRepository $repository;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
        $this->repository = new WebhookEventLogRepository($this->database);
    }

    public function testRecordsANewDelivery(): void
    {
        $this->repository->record(GatewayName::Yoco, 'evt_1', 'attempt-uuid', '{"id":"evt_1"}');

        self::assertCount(1, $this->database->all('bizhub_payments_webhook_log'));
    }

    public function testDoesNotDuplicateTheSameGatewayAndEventId(): void
    {
        $this->repository->record(GatewayName::Yoco, 'evt_1', 'attempt-uuid', '{"id":"evt_1"}');
        $this->repository->record(GatewayName::Yoco, 'evt_1', 'attempt-uuid', '{"id":"evt_1"}');

        self::assertCount(1, $this->database->all('bizhub_payments_webhook_log'));
    }

    public function testDifferentGatewaysWithTheSameEventIdAreRecordedSeparately(): void
    {
        $this->repository->record(GatewayName::Yoco, 'evt_1', null, '{}');
        $this->repository->record(GatewayName::SnapScan, 'evt_1', null, '{}');

        self::assertCount(2, $this->database->all('bizhub_payments_webhook_log'));
    }
}
