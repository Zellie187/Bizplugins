<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Controllers;

use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Services\ProfileService;

/**
 * Handles client profile view and update operations.
 *
 * Transport-agnostic: route registration belongs to the API/Admin layers.
 *
 * @package BizHub\ClientPortal\Controllers
 */
final class ProfileController
{
    public function __construct(
        private readonly ProfileService $profiles
    ) {
    }

    /**
     * Update a client's profile.
     *
     * @return array<string,mixed>
     */
    public function update(string $clientUuid, ProfileData $profileData): array
    {
        $client = $this->profiles->updateProfile($clientUuid, $profileData);

        return $client->toArray();
    }
}
