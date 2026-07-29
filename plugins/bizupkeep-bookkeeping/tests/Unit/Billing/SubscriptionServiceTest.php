<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Billing;

use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubscriptionServiceTest extends TestCase
{
    private const COMPANY = 'company-1';

    private SubscriptionService $service;

    protected function setUp(): void
    {
        $this->service = new SubscriptionService(new SubscriptionRepository(new InMemoryDatabase()));
    }

    public function testNeverSubscribedCompanyIsInactive(): void
    {
        self::assertFalse($this->service->isActive(self::COMPANY));
    }

    public function testGetOrCreateIsIdempotent(): void
    {
        $first = $this->service->getOrCreate(self::COMPANY);
        $second = $this->service->getOrCreate(self::COMPANY);

        self::assertSame($first->uuid, $second->uuid);
        self::assertNull($second->paidUntil);
    }

    public function testExtendFromNeverSubscribedStartsAtTodayPlusDays(): void
    {
        $subscription = $this->service->extend(self::COMPANY, 30);

        self::assertNotNull($subscription->paidUntil);
        self::assertSame(
            (new DateTimeImmutable())->modify('+30 days')->format('Y-m-d'),
            $subscription->paidUntil->format('Y-m-d')
        );
        self::assertTrue($this->service->isActive(self::COMPANY));
    }

    public function testExtendWhileStillActiveAddsToExistingPaidUntilRatherThanResetting(): void
    {
        $this->service->extend(self::COMPANY, 30);
        $second = $this->service->extend(self::COMPANY, 30);

        self::assertNotNull($second->paidUntil);
        self::assertSame(
            (new DateTimeImmutable())->modify('+60 days')->format('Y-m-d'),
            $second->paidUntil->format('Y-m-d')
        );
    }

    public function testExtendFromALapsedSubscriptionStartsFreshFromTodayNotFromThePastDate(): void
    {
        // Simulate a lapsed subscription by extending, then manually
        // driving isActive() with a lapsed date via a fresh repository
        // row - easiest is to extend with a negative "days" to land in
        // the past, then extend again and confirm it starts from today.
        $this->service->extend(self::COMPANY, -60);
        self::assertFalse($this->service->isActive(self::COMPANY));

        $renewed = $this->service->extend(self::COMPANY, 30);

        self::assertNotNull($renewed->paidUntil);
        self::assertSame(
            (new DateTimeImmutable())->modify('+30 days')->format('Y-m-d'),
            $renewed->paidUntil->format('Y-m-d')
        );
    }

    public function testSuspendedSubscriptionIsInactiveEvenWithAFuturePaidUntil(): void
    {
        $this->service->extend(self::COMPANY, 30);
        $this->service->suspend(self::COMPANY);

        self::assertFalse($this->service->isActive(self::COMPANY));
    }

    public function testReactivateClearsSuspensionWithoutChangingPaidUntil(): void
    {
        $extended = $this->service->extend(self::COMPANY, 30);
        $this->service->suspend(self::COMPANY);

        $reactivated = $this->service->reactivate(self::COMPANY);

        self::assertTrue($this->service->isActive(self::COMPANY));
        self::assertSame($extended->paidUntil?->format('Y-m-d'), $reactivated->paidUntil?->format('Y-m-d'));
    }

    public function testExtendAlwaysClearsAPriorSuspension(): void
    {
        $this->service->extend(self::COMPANY, 30);
        $this->service->suspend(self::COMPANY);
        self::assertFalse($this->service->isActive(self::COMPANY));

        $this->service->extend(self::COMPANY, 30);

        self::assertTrue($this->service->isActive(self::COMPANY));
    }

    public function testSubscriptionsAreScopedPerCompany(): void
    {
        $this->service->extend('company-a', 30);

        self::assertTrue($this->service->isActive('company-a'));
        self::assertFalse($this->service->isActive('company-b'));
    }
}
