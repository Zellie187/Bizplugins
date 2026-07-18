<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a single notification delivered to a client's portal inbox.
 *
 * @package BizHub\ClientPortal\Entities
 */
final class Notification
{
    public function __construct(
        private readonly string $uuid,
        private readonly string $clientUuid,
        private readonly string $title,
        private readonly string $message,
        private readonly string $type = 'info',
        private bool $read = false,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {
        $this->validate();
    }

    /**
     * Validate entity state.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Notification UUID cannot be empty.');
        }

        if ($this->clientUuid === '') {
            throw new InvalidArgumentException('Notification must be associated with a client.');
        }

        if (trim($this->title) === '') {
            throw new InvalidArgumentException('Notification title cannot be empty.');
        }
    }

    /**
     * Get UUID.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get the UUID of the client this notification belongs to.
     */
    public function getClientUuid(): string
    {
        return $this->clientUuid;
    }

    /**
     * Get title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get notification type (e.g. "info", "warning", "success").
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Determine whether the notification has been read.
     */
    public function isRead(): bool
    {
        return $this->read;
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->read = true;
    }

    /**
     * Get creation timestamp.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Export entity as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->clientUuid,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'read' => $this->read,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
