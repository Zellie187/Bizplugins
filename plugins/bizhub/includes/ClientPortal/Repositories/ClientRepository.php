<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Repositories;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Entities\Profile;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * Persists Client aggregates (including their embedded Profile) using the
 * framework database abstraction.
 *
 * @package BizHub\ClientPortal\Repositories
 */
final class ClientRepository implements ClientRepositoryInterface
{
    private const TABLE = 'bizhub_clients';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Client
    {
        $row = $this->database->findOne(self::TABLE, ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?Client
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByWpUserId(int $wpUserId): ?Client
    {
        $row = $this->database->findOne(self::TABLE, ['wp_user_id' => $wpUserId]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function existsForWpUserId(int $wpUserId): bool
    {
        return $this->database->exists(self::TABLE, ['wp_user_id' => $wpUserId]);
    }

    /**
     * {@inheritDoc}
     */
    public function save(Client $client): Client
    {
        $data = $this->dehydrate($client);

        if ($this->database->exists(self::TABLE, ['uuid' => $client->getUuid()])) {
            $this->database->update(self::TABLE, $data, ['uuid' => $client->getUuid()]);
        } else {
            $this->database->insert(self::TABLE, $data);
        }

        return $client;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Client $client): void
    {
        $this->database->delete(self::TABLE, ['uuid' => $client->getUuid()]);
    }

    /**
     * Hydrate a database row into a Client aggregate.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Client
    {
        $profile = new Profile(
            $row['first_name'],
            $row['last_name'],
            $row['phone'] ?? '',
            $row['avatar_url'] ?? null
        );

        return new Client(
            $row['uuid'],
            (int) $row['wp_user_id'],
            $profile,
            ClientStatus::from($row['status']),
            new DateTimeImmutable((string) $row['created_at']),
            empty($row['updated_at']) ? null : new DateTimeImmutable((string) $row['updated_at'])
        );
    }

    /**
     * Convert a Client aggregate into a database row.
     *
     * @return array<string,mixed>
     */
    private function dehydrate(Client $client): array
    {
        $profile = $client->getProfile();

        return [
            'uuid' => $client->getUuid(),
            'wp_user_id' => $client->getWpUserId(),
            'first_name' => $profile->getFirstName(),
            'last_name' => $profile->getLastName(),
            'phone' => $profile->getPhone(),
            'avatar_url' => $profile->getAvatarUrl(),
            'status' => $client->getStatus()->value,
            'created_at' => $client->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $client->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
