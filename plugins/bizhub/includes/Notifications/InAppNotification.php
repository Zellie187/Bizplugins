<?php

declare(strict_types=1);

namespace BizHub\Notifications;

use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\DTO\NotificationData;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\ClientPortal\Services\NotificationService;

/**
 * Delivers notifications to a client's portal inbox.
 *
 * @package BizHub\Notifications
 */
final class InAppNotification implements NotificationChannel
{
    public function __construct(
        private readonly ClientServiceInterface $clients,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'in_app';
    }

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification): bool
    {
        try {
            $client = $this->clients->getClientByWpUserId($notification->recipientId);
        } catch (ClientNotFoundException) {
            return false;
        }

        $this->notifications->send(new NotificationData(
            $client->getUuid(),
            $notification->subject,
            $notification->body
        ));

        return true;
    }
}
