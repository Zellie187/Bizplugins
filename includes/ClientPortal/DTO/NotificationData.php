<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\DTO;

/**
 * Data Transfer Object representing a Notification.
 *
 * @package BizHub\ClientPortal\DTO
 */
final readonly class NotificationData
{
    public function __construct(
        public string $clientUuid,
        public string $title,
        public string $message,
        public string $type = 'info',
    ) {
    }

    /**
     * Export the DTO as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'client_uuid' => $this->clientUuid,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ];
    }
}
