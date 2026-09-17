<?php

declare(strict_types=1);

namespace BizHub\Payments\Repositories;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Entities\PaymentAttempt;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\PaymentAttemptStatus;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the payment_attempts
 * table.
 *
 * @package BizHub\Payments\Repositories
 */
final class PaymentAttemptRepository implements PaymentAttemptRepositoryInterface
{
    private const TABLE = 'bizhub_payments_attempts';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByUuid(string $uuid): ?PaymentAttempt
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByGatewayReference(GatewayName $gateway, string $gatewayReference): ?PaymentAttempt
    {
        $row = $this->database->findOne(self::TABLE, [
            'gateway' => $gateway->value,
            'gateway_reference' => $gatewayReference,
        ]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return PaymentAttempt[]
     */
    public function findAll(): array
    {
        $rows = $this->database->findAll(self::TABLE, [], ['created_at' => 'DESC']);

        return array_map($this->hydrate(...), $rows);
    }

    public function save(PaymentAttempt $attempt): PaymentAttempt
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $attempt->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($attempt), ['uuid' => $attempt->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($attempt));
        }

        return $attempt;
    }

    public function markSucceededOnce(string $uuid, DateTimeImmutable $confirmedAt): bool
    {
        $affected = $this->database->update(
            self::TABLE,
            [
                'status' => PaymentAttemptStatus::Succeeded->value,
                'confirmed_at' => $confirmedAt->format('Y-m-d H:i:s'),
                'updated_at' => $confirmedAt->format('Y-m-d H:i:s'),
            ],
            [
                'uuid' => $uuid,
                'status' => PaymentAttemptStatus::Redirected->value,
            ]
        );

        return $affected > 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(PaymentAttempt $attempt): array
    {
        return [
            'uuid' => $attempt->uuid,
            'gateway' => $attempt->gateway->value,
            'gateway_reference' => $attempt->gatewayReference,
            'service_key' => $attempt->serviceKey,
            'company_uuid' => $attempt->companyUuid,
            'workflow_uuid' => $attempt->workflowUuid,
            'buyer_wp_user_id' => $attempt->buyerWpUserId,
            'amount_minor' => $attempt->amountMinor,
            'currency' => $attempt->currency,
            'status' => $attempt->status->value,
            'invoice_uuid' => $attempt->invoiceUuid,
            'customer_uuid' => $attempt->customerUuid,
            'failure_reason' => $attempt->failureReason,
            'fulfilled_at' => $attempt->fulfilledAt?->format('Y-m-d H:i:s'),
            'created_at' => $attempt->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $attempt->updatedAt?->format('Y-m-d H:i:s'),
            'confirmed_at' => $attempt->confirmedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): PaymentAttempt
    {
        return new PaymentAttempt(
            uuid: (string) $row['uuid'],
            gateway: GatewayName::from((string) $row['gateway']),
            gatewayReference: $this->nullableString($row['gateway_reference'] ?? null),
            serviceKey: (string) $row['service_key'],
            companyUuid: (string) $row['company_uuid'],
            workflowUuid: $this->nullableString($row['workflow_uuid'] ?? null),
            buyerWpUserId: (int) $row['buyer_wp_user_id'],
            amountMinor: (int) $row['amount_minor'],
            currency: (string) $row['currency'],
            status: PaymentAttemptStatus::from((string) $row['status']),
            invoiceUuid: $this->nullableString($row['invoice_uuid'] ?? null),
            customerUuid: $this->nullableString($row['customer_uuid'] ?? null),
            failureReason: $this->nullableString($row['failure_reason'] ?? null),
            fulfilledAt: $this->nullableDate($row['fulfilled_at'] ?? null),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: $this->nullableDate($row['updated_at'] ?? null),
            confirmedAt: $this->nullableDate($row['confirmed_at'] ?? null),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function nullableDate(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value);
    }
}
