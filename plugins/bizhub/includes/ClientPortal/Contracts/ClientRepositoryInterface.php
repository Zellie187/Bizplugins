<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Contracts;

use BizHub\ClientPortal\Entities\Client;

/**
 * Defines the persistence contract for Client entities.
 *
 * @package BizHub\ClientPortal\Contracts
 */
interface ClientRepositoryInterface
{
    /**
     * Find a client by its internal database identifier.
     */
    public function find(int $id): ?Client;

    /**
     * Find a client by UUID.
     */
    public function findByUuid(string $uuid): ?Client;

    /**
     * Find a client by their WordPress user ID.
     */
    public function findByWpUserId(int $wpUserId): ?Client;

    /**
     * Determine whether a WordPress user already has a client account.
     */
    public function existsForWpUserId(int $wpUserId): bool;

    /**
     * Persist a client.
     */
    public function save(Client $client): Client;

    /**
     * Delete a client.
     */
    public function delete(Client $client): void;
}
