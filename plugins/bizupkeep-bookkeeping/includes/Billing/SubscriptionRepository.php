<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Billing;

use BizHub\Bookkeeping\Contracts\SubscriptionRepositoryInterface;
use BizHub\Bookkeeping\Entities\Subscription;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the subscriptions table.
 *
 * @package BizHub\Bookkeeping\Billing
 */
final class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_subscriptions';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid): ?Subscription
    {
        $row = $this->database->findOne(self::TABLE, ['company_uuid' => $companyUuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->database->findAll(self::TABLE));
    }

    public function save(Subscription $subscription): Subscription
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $subscription->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($subscription), ['uuid' => $subscription->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($subscription));
        }

        return $subscription;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(Subscription $subscription): array
    {
        return [
            'uuid' => $subscription->uuid,
            'company_uuid' => $subscription->companyUuid,
            'paid_until' => $subscription->paidUntil?->format('Y-m-d'),
            'is_suspended' => $subscription->isSuspended ? 1 : 0,
            'last_reminder_type' => $subscription->lastReminderType,
            'last_reminder_for_paid_until' => $subscription->lastReminderForPaidUntil?->format('Y-m-d'),
            'created_at' => $subscription->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $subscription->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Subscription
    {
        return new Subscription(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            paidUntil: isset($row['paid_until']) && $row['paid_until'] !== null
                ? new DateTimeImmutable((string) $row['paid_until'])
                : null,
            isSuspended: (bool) $row['is_suspended'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
            lastReminderType: isset($row['last_reminder_type']) && $row['last_reminder_type'] !== null
                ? (string) $row['last_reminder_type']
                : null,
            lastReminderForPaidUntil: isset($row['last_reminder_for_paid_until'])
                && $row['last_reminder_for_paid_until'] !== null
                ? new DateTimeImmutable((string) $row['last_reminder_for_paid_until'])
                : null,
        );
    }
}
