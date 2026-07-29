<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Mocks;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\ClientPortal\Entities\Client;
use RuntimeException;

/**
 * Minimal ClientRepositoryInterface double for tests, backed by a fixed
 * numeric-id => Client map handed in by the test. Only find() is
 * meaningfully implemented - SubscriptionReminderService is the only
 * caller under test, and it never calls anything else on this interface.
 */
final class FakeClientRepository implements ClientRepositoryInterface
{
    /**
     * @param array<int,Client> $clientsById
     */
    public function __construct(
        private readonly array $clientsById
    ) {
    }

    public function find(int $id): ?Client
    {
        return $this->clientsById[$id] ?? null;
    }

    public function findByUuid(string $uuid): ?Client
    {
        return null;
    }

    public function findByWpUserId(int $wpUserId): ?Client
    {
        return null;
    }

    public function existsForWpUserId(int $wpUserId): bool
    {
        return false;
    }

    public function save(Client $client): Client
    {
        throw new RuntimeException('Not implemented in this test double.');
    }

    public function delete(Client $client): void
    {
        throw new RuntimeException('Not implemented in this test double.');
    }
}
