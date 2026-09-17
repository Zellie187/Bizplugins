<?php

declare(strict_types=1);

namespace BizHub\Payments\Tests\Unit\Repositories;

use BizHub\Payments\Entities\PaymentAttempt;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\PaymentAttemptStatus;
use BizHub\Payments\Repositories\PaymentAttemptRepository;
use BizHub\Payments\Tests\Mocks\InMemoryDatabase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PaymentAttemptRepositoryTest extends TestCase
{
    private InMemoryDatabase $database;

    private PaymentAttemptRepository $repository;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
        $this->repository = new PaymentAttemptRepository($this->database);
    }

    public function testSaveThenFindByUuidRoundTrips(): void
    {
        $attempt = $this->makeAttempt();

        $this->repository->save($attempt);

        $found = $this->repository->findByUuid($attempt->uuid);

        self::assertNotNull($found);
        self::assertSame($attempt->uuid, $found->uuid);
        self::assertSame(GatewayName::Yoco, $found->gateway);
        self::assertSame(65000, $found->amountMinor);
        self::assertSame(PaymentAttemptStatus::Redirected, $found->status);
    }

    public function testFindByGatewayReferenceRoundTrips(): void
    {
        $this->repository->save($this->makeAttempt());

        $found = $this->repository->findByGatewayReference(GatewayName::Yoco, 'checkout_123');

        self::assertNotNull($found);
        self::assertSame('checkout_123', $found->gatewayReference);
    }

    public function testMarkSucceededOnceTransitionsFromRedirectedAndReturnsTrue(): void
    {
        $attempt = $this->makeAttempt();
        $this->repository->save($attempt);

        $claimed = $this->repository->markSucceededOnce($attempt->uuid, new DateTimeImmutable('2026-02-01 10:00:00'));

        self::assertTrue($claimed);

        $found = $this->repository->findByUuid($attempt->uuid);
        self::assertNotNull($found);
        self::assertSame(PaymentAttemptStatus::Succeeded, $found->status);
        self::assertNotNull($found->confirmedAt);
    }

    public function testMarkSucceededOnceIsIdempotentOnDuplicateDelivery(): void
    {
        $attempt = $this->makeAttempt();
        $this->repository->save($attempt);

        $first = $this->repository->markSucceededOnce($attempt->uuid, new DateTimeImmutable());
        $second = $this->repository->markSucceededOnce($attempt->uuid, new DateTimeImmutable());

        self::assertTrue($first);
        self::assertFalse($second, 'A second delivery of the same webhook must not re-claim the attempt.');
    }

    public function testMarkSucceededOnceReturnsFalseForAlreadyFailedAttempt(): void
    {
        $attempt = $this->makeAttempt();
        $this->repository->save($attempt);
        $this->repository->save($attempt->withFailed('Card declined.'));

        $claimed = $this->repository->markSucceededOnce($attempt->uuid, new DateTimeImmutable());

        self::assertFalse($claimed, 'A Failed attempt must never be transitioned to Succeeded.');
    }

    private function makeAttempt(): PaymentAttempt
    {
        $attempt = new PaymentAttempt(
            uuid: '11111111-1111-1111-1111-111111111111',
            gateway: GatewayName::Yoco,
            gatewayReference: null,
            serviceKey: 'registration',
            companyUuid: '22222222-2222-2222-2222-222222222222',
            workflowUuid: '33333333-3333-3333-3333-333333333333',
            buyerWpUserId: 7,
            amountMinor: 65000,
            currency: 'ZAR',
            status: PaymentAttemptStatus::Created,
            invoiceUuid: null,
            customerUuid: null,
            failureReason: null,
            fulfilledAt: null,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
        );

        return $attempt->withRedirected('checkout_123');
    }
}
