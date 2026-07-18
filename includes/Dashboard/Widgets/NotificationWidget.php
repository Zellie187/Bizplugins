<?php

declare(strict_types=1);

namespace BizHub\Dashboard\Widgets;

use BizHub\ClientPortal\Services\NotificationService;

/**
 * Dashboard widget summarizing a client's unread notifications.
 *
 * @package BizHub\Dashboard\Widgets
 */
final class NotificationWidget implements Widget
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function key(): string
    {
        return 'notifications';
    }

    /**
     * {@inheritDoc}
     */
    public function data(int $clientId, string $clientUuid): array
    {
        $notifications = $this->notifications->forClient($clientUuid);

        return [
            'unread_count' => $this->notifications->unreadCount($clientUuid),
            'items' => array_map(
                static fn ($notification): array => $notification->toArray(),
                array_slice($notifications, 0, 5)
            ),
        ];
    }
}
