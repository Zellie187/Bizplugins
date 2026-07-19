<?php

declare(strict_types=1);

namespace BizHub\Notifications;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Support\Uuid;

/**
 * Queues notifications for delivery and processes them through their
 * configured channels.
 *
 * @package BizHub\Notifications
 */
final class NotificationQueue
{
    private const TABLE = 'bizhub_notification_queue';

    /**
     * @var array<string,NotificationChannel>
     */
    private array $channels = [];

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * Register a delivery channel.
     */
    public function registerChannel(NotificationChannel $channel): void
    {
        $this->channels[$channel->name()] = $channel;
    }

    /**
     * Queue a notification for delivery.
     */
    public function enqueue(Notification $notification): string
    {
        $uuid = Uuid::generate();

        $this->database->insert(self::TABLE, [
            'uuid' => $uuid,
            'payload' => json_encode($notification->toArray(), JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $uuid;
    }

    /**
     * Process every pending notification through its configured channels.
     *
     * @return int Number of notifications delivered successfully to
     *             every one of their channels.
     */
    public function processPending(): int
    {
        $delivered = 0;

        foreach ($this->database->findAll(self::TABLE, ['status' => 'pending']) as $row) {
            if ($this->deliver($row)) {
                $delivered++;
            }
        }

        return $delivered;
    }

    /**
     * Deliver a single queued row through its channels.
     *
     * @param array<string,mixed> $row
     */
    private function deliver(array $row): bool
    {
        $notification = Notification::fromArray(json_decode((string) $row['payload'], true) ?? []);

        $allDelivered = true;

        foreach ($notification->channels as $channelName) {
            $channel = $this->channels[$channelName] ?? null;

            if ($channel === null || ! $channel->send($notification)) {
                $allDelivered = false;
            }
        }

        $this->database->update(
            self::TABLE,
            ['status' => $allDelivered ? 'sent' : 'failed'],
            ['uuid' => $row['uuid']]
        );

        return $allDelivered;
    }
}
