<?php

declare(strict_types=1);

namespace BizHub\Payments\Entities;

use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\PaymentAttemptStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One attempt by a client to pay for a Service catalog entry via a
 * payment gateway - created when the client clicks "Pay", carried
 * through the gateway's hosted checkout, and confirmed (or not) by
 * that gateway's webhook. $workflowUuid is null for the one flow with
 * no underlying workflow instance (Bookkeeping Monthly's subscription
 * renewal); everything else (Registration/Amendment/Annual Return)
 * always has one.
 *
 * $fulfilledAt is deliberately separate from $status rather than a
 * further status case: PaymentAttemptStatus::Succeeded means the
 * gateway confirmed the money was captured (set atomically by
 * PaymentAttemptRepository::markSucceededOnce()), while $fulfilledAt
 * means PaymentConfirmationService's downstream steps (Invoice +
 * workflow-confirmation/subscription-extension) actually completed. A
 * payment can be genuinely Succeeded with $fulfilledAt still null if
 * that downstream step threw - PaymentsDashboardPage surfaces exactly
 * that gap for staff to retry manually, which collapsing both into one
 * status enum would hide.
 *
 * @package BizHub\Payments\Entities
 */
final readonly class PaymentAttempt
{
    public function __construct(
        public string $uuid,
        public GatewayName $gateway,
        public ?string $gatewayReference,
        public string $serviceKey,
        public string $companyUuid,
        public ?string $workflowUuid,
        public int $buyerWpUserId,
        public int $amountMinor,
        public string $currency,
        public PaymentAttemptStatus $status,
        public ?string $invoiceUuid,
        public ?string $customerUuid,
        public ?string $failureReason,
        public ?DateTimeImmutable $fulfilledAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $confirmedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('PaymentAttempt uuid cannot be empty.');
        }

        if ($this->serviceKey === '') {
            throw new InvalidArgumentException('PaymentAttempt serviceKey cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('PaymentAttempt companyUuid cannot be empty.');
        }

        if ($this->buyerWpUserId <= 0) {
            throw new InvalidArgumentException('PaymentAttempt buyerWpUserId must be a real WordPress user ID.');
        }

        if ($this->amountMinor <= 0) {
            throw new InvalidArgumentException('PaymentAttempt amountMinor must be positive.');
        }
    }

    /**
     * After the gateway's createCheckout() call succeeds.
     */
    public function withRedirected(string $gatewayReference): self
    {
        return new self(
            $this->uuid,
            $this->gateway,
            $gatewayReference,
            $this->serviceKey,
            $this->companyUuid,
            $this->workflowUuid,
            $this->buyerWpUserId,
            $this->amountMinor,
            $this->currency,
            PaymentAttemptStatus::Redirected,
            $this->invoiceUuid,
            $this->customerUuid,
            $this->failureReason,
            $this->fulfilledAt,
            $this->createdAt,
            new DateTimeImmutable(),
            $this->confirmedAt,
        );
    }

    public function withFailed(string $reason): self
    {
        return new self(
            $this->uuid,
            $this->gateway,
            $this->gatewayReference,
            $this->serviceKey,
            $this->companyUuid,
            $this->workflowUuid,
            $this->buyerWpUserId,
            $this->amountMinor,
            $this->currency,
            PaymentAttemptStatus::Failed,
            $this->invoiceUuid,
            $this->customerUuid,
            $reason,
            $this->fulfilledAt,
            $this->createdAt,
            new DateTimeImmutable(),
            $this->confirmedAt,
        );
    }

    public function withAmountMismatch(): self
    {
        return new self(
            $this->uuid,
            $this->gateway,
            $this->gatewayReference,
            $this->serviceKey,
            $this->companyUuid,
            $this->workflowUuid,
            $this->buyerWpUserId,
            $this->amountMinor,
            $this->currency,
            PaymentAttemptStatus::AmountMismatch,
            $this->invoiceUuid,
            $this->customerUuid,
            'Gateway-confirmed amount did not match the expected amount.',
            $this->fulfilledAt,
            $this->createdAt,
            new DateTimeImmutable(),
            $this->confirmedAt,
        );
    }

    public function withCustomer(string $customerUuid): self
    {
        return new self(
            $this->uuid,
            $this->gateway,
            $this->gatewayReference,
            $this->serviceKey,
            $this->companyUuid,
            $this->workflowUuid,
            $this->buyerWpUserId,
            $this->amountMinor,
            $this->currency,
            $this->status,
            $this->invoiceUuid,
            $customerUuid,
            $this->failureReason,
            $this->fulfilledAt,
            $this->createdAt,
            new DateTimeImmutable(),
            $this->confirmedAt,
        );
    }

    public function withInvoice(string $invoiceUuid): self
    {
        return new self(
            $this->uuid,
            $this->gateway,
            $this->gatewayReference,
            $this->serviceKey,
            $this->companyUuid,
            $this->workflowUuid,
            $this->buyerWpUserId,
            $this->amountMinor,
            $this->currency,
            $this->status,
            $invoiceUuid,
            $this->customerUuid,
            $this->failureReason,
            $this->fulfilledAt,
            $this->createdAt,
            new DateTimeImmutable(),
            $this->confirmedAt,
        );
    }

    public function withFulfilled(DateTimeImmutable $fulfilledAt): self
    {
        return new self(
            $this->uuid,
            $this->gateway,
            $this->gatewayReference,
            $this->serviceKey,
            $this->companyUuid,
            $this->workflowUuid,
            $this->buyerWpUserId,
            $this->amountMinor,
            $this->currency,
            $this->status,
            $this->invoiceUuid,
            $this->customerUuid,
            $this->failureReason,
            $fulfilledAt,
            $this->createdAt,
            new DateTimeImmutable(),
            $this->confirmedAt,
        );
    }
}
