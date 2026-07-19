<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Controllers;

use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\Services\NotificationService;
use BizHub\Companies\Services\CompanyLookupService;

/**
 * Assembles data for the client portal dashboard.
 *
 * This controller is transport-agnostic: it returns plain arrays that
 * can be serialized by whichever layer wires it up (REST API, admin-ajax,
 * or a template). Route registration belongs to the API/Admin layers.
 *
 * @package BizHub\ClientPortal\Controllers
 */
final class ClientDashboardController
{
    public function __construct(
        private readonly ClientServiceInterface $clients,
        private readonly CompanyLookupService $companies,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * Build the dashboard payload for a WordPress user.
     *
     * @return array<string,mixed>
     */
    public function forWpUser(int $wpUserId): array
    {
        $client = $this->clients->getClientByWpUserId($wpUserId);

        return [
            'client' => $client->toArray(),
            'companies' => $this->companies->search($wpUserId, ''),
            'unread_notifications' => $this->notifications->unreadCount($client->getUuid()),
        ];
    }
}
