<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Services;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Entities\Profile;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use InvalidArgumentException;

/**
 * Implements the business operations for Client account management.
 *
 * @package BizHub\ClientPortal\Services
 */
final class ClientService implements ClientServiceInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function createClient(ClientData $clientData): Client
    {
        if ($this->clients->existsForWpUserId($clientData->wpUserId)) {
            throw new InvalidArgumentException(
                sprintf('WordPress user #%d already has a client account.', $clientData->wpUserId)
            );
        }

        $profile = new Profile(
            $clientData->profile->firstName,
            $clientData->profile->lastName,
            $clientData->profile->phone,
            $clientData->profile->avatarUrl
        );

        $client = new Client(
            $clientData->uuid,
            $clientData->wpUserId,
            $profile,
            $clientData->status
        );

        return $this->clients->save($client);
    }

    /**
     * {@inheritDoc}
     */
    public function getClient(string $uuid): Client
    {
        return $this->clients->findByUuid($uuid)
            ?? throw ClientNotFoundException::withUuid($uuid);
    }

    /**
     * {@inheritDoc}
     */
    public function getClientByWpUserId(int $wpUserId): Client
    {
        return $this->clients->findByWpUserId($wpUserId)
            ?? throw ClientNotFoundException::withWpUserId($wpUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(string $uuid, ClientStatus $status): Client
    {
        $client = $this->getClient($uuid);

        $client->setStatus($status);

        return $this->clients->save($client);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteClient(string $uuid): void
    {
        $this->clients->delete($this->getClient($uuid));
    }
}
