<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Contracts;

use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Entities\ClientStatus;

/**
 * Defines the business operations for Client account management.
 *
 * @package BizHub\ClientPortal\Contracts
 */
interface ClientServiceInterface
{
    /**
     * Create a client account.
     */
    public function createClient(ClientData $clientData): Client;

    /**
     * Retrieve a client by UUID.
     */
    public function getClient(string $uuid): Client;

    /**
     * Retrieve a client by their WordPress user ID.
     */
    public function getClientByWpUserId(int $wpUserId): Client;

    /**
     * Update a client's status.
     */
    public function updateStatus(string $uuid, ClientStatus $status): Client;

    /**
     * Delete a client account.
     */
    public function deleteClient(string $uuid): void;
}
