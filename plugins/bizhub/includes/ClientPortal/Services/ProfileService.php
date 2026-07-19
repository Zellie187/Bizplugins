<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Services;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;

/**
 * Implements profile management operations for a Client.
 *
 * @package BizHub\ClientPortal\Services
 */
final class ProfileService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {
    }

    /**
     * Update a client's profile.
     */
    public function updateProfile(string $clientUuid, ProfileData $profileData): Client
    {
        $client = $this->clients->findByUuid($clientUuid)
            ?? throw ClientNotFoundException::withUuid($clientUuid);

        $client->getProfile()->update(
            $profileData->firstName,
            $profileData->lastName,
            $profileData->phone,
            $profileData->avatarUrl
        );

        return $this->clients->save($client);
    }
}
