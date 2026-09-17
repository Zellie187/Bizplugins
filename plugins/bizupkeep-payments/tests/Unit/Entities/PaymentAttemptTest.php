<?php

declare(strict_types=1);

namespace BizHub\Payments\Tests\Unit\Entities;

use BizHub\Payments\Entities\PaymentAttempt;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\PaymentAttemptStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentAttemptTest extends TestCase
{
    public function testRejectsEmptyUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeAttempt(uuid: '');
    }

    public function testRejectsEmptyServiceKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeAttempt(serviceKey: '');
    }

    public function testRejectsEmptyCompanyUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeAttempt(companyUuid: '');
    }

    public function testRejectsNonPositiveBuyerWpUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeAttempt(buyerWpUserId: 0);
    }

    public function testRejectsNonPositiveAmountMinor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeAttempt(amountMinor: 0);
    }

    public function testWithRedirectedSetsGatewayReferenceAndStatus(): void
    {
        $attempt = $this->makeAttempt()->withRedirected('checkout_123');

        self::assertSame('checkout_123', $attempt->gatewayReference);
        self::assertSame(PaymentAttemptStatus::Redirected, $attempt->status);
    }

    public function testWithFailedSetsReasonAndStatus(): void
    {
        $attempt = $this->makeAttempt()->withFailed('Card declined.');

        self::assertSame(PaymentAttemptStatus::Failed, $attempt->status);
        self::assertSame('Card declined.', $attempt->failureReason);
    }

    public function testWithFulfilledDoesNotChangeStatus(): void
    {
        $now = new DateTimeImmutable();
        $attempt = $this->makeAttempt()->withFulfilled($now);

        self::assertSame(PaymentAttemptStatus::Created, $attempt->status);
        self::assertSame($now, $attempt->fulfilledAt);
    }

    public function testWithCustomerAndWithInvoicePreserveOtherFields(): void
    {
        $attempt = $this->makeAttempt()
            ->withCustomer('customer-uuid')
            ->withInvoice('invoice-uuid');

        self::assertSame('customer-uuid', $attempt->customerUuid);
        self::assertSame('invoice-uuid', $attempt->invoiceUuid);
        self::assertSame('reg-service', $attempt->serviceKey);
    }

    private function makeAttempt(
        string $uuid = '11111111-1111-1111-1111-111111111111',
        string $serviceKey = 'reg-service',
        string $companyUuid = '22222222-2222-2222-2222-222222222222',
        int $buyerWpUserId = 5,
        int $amountMinor = 65000
    ): PaymentAttempt {
        return new PaymentAttempt(
            uuid: $uuid,
            gateway: GatewayName::Yoco,
            gatewayReference: null,
            serviceKey: $serviceKey,
            companyUuid: $companyUuid,
            workflowUuid: null,
            buyerWpUserId: $buyerWpUserId,
            amountMinor: $amountMinor,
            currency: 'ZAR',
            status: PaymentAttemptStatus::Created,
            invoiceUuid: null,
            customerUuid: null,
            failureReason: null,
            fulfilledAt: null,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
        );
    }
}
