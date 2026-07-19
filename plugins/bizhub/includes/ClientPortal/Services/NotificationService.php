<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Services;

use BizHub\ClientPortal\DTO\NotificationData;
use BizHub\ClientPortal\Entities\Notification;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;

/**
 * Manages a client's portal notification inbox.
 *
 * @package BizHub\ClientPortal\Services
 */
final class NotificationService
{
    private const TABLE = 'bizhub_client_notifications';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * Send a notification to a client's inbox.
     */
    public function send(NotificationData $notificationData): Notification
    {
        $notification = new Notification(
            Uuid::generate(),
            $notificationData->clientUuid,
            $notificationData->title,
            $notificationData->message,
            $notificationData->type
        );

        $this->database->insert(self::TABLE, $notification->toArray());

        return $notification;
    }

    /**
     * Retrieve a client's notifications, most recent first.
     *
     * @return Notification[]
     */
    public function forClient(string $clientUuid): array
    {
        $rows = $this->database->findAll(
            self::TABLE,
            ['client_uuid' => $clientUuid],
            ['created_at' => 'DESC']
        );

        return array_map(
            fn (array $row): Notification => $this->hydrate($row),
            $rows
        );
    }

    /**
     * Count a client's unread notifications.
     */
    public function unreadCount(string $clientUuid): int
    {
        return count($this->database->findAll(
            self::TABLE,
            ['client_uuid' => $clientUuid, 'is_read' => 0]
        ));
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(string $uuid): void
    {
        $this->database->update(self::TABLE, ['is_read' => 1], ['uuid' => $uuid]);
    }

    /**
     * Delete a notification.
     */
    public function delete(string $uuid): void
    {
        $this->database->delete(self::TABLE, ['uuid' => $uuid]);
    }

    /**
     * Hydrate a database row into a Notification entity.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Notification
    {
        return new Notification(
            $row['uuid'],
            $row['client_uuid'],
            $row['title'],
            $row['message'],
            $row['type'] ?? 'info',
            (bool) $row['is_read'],
            new DateTimeImmutable((string) $row['created_at'])
        );
    }
}
