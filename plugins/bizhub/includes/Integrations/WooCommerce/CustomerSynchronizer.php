<?php

declare(strict_types=1);

namespace BizHub\Integrations\WooCommerce;

use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Entities\ClientStatus;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\Framework\Support\Uuid;

/**
 * Ensures every WooCommerce customer has a corresponding BizHub client
 * account in the client portal.
 *
 * @package BizHub\Integrations\WooCommerce
 */
final class CustomerSynchronizer
{
    public function __construct(
        private readonly ClientServiceInterface $clients
    ) {
    }

    /**
     * Get or create the BizHub client for a WordPress user.
     */
    public function syncFromWpUser(int $wpUserId): Client
    {
        try {
            return $this->clients->getClientByWpUserId($wpUserId);
        } catch (ClientNotFoundException) {
            $user = get_userdata($wpUserId);

            $firstName = $user !== false ? (string) $user->first_name : '';
            $lastName = $user !== false ? (string) $user->last_name : '';

            if ($firstName === '' && $lastName === '') {
                $firstName = $user !== false ? (string) $user->display_name : 'Customer';
                $lastName = '';
            }

            return $this->clients->createClient(new ClientData(
                Uuid::generate(),
                $wpUserId,
                new ProfileData(
                    $firstName !== '' ? $firstName : 'Customer',
                    $lastName !== '' ? $lastName : (string) $wpUserId
                ),
                ClientStatus::ACTIVE
            ));
        }
    }
}
