<?php

declare(strict_types=1);

namespace BizHub\Notifications;

use InvalidArgumentException;

/**
 * Represents a notification message to be delivered through one or
 * more channels.
 *
 * @package BizHub\Notifications
 */
final readonly class Notification
{
    /**
     * @param int                  $recipientId WordPress user ID of the recipient.
     * @param string               $subject
     * @param string               $body
     * @param array<int,string>    $channels    Channel names to deliver through (e.g. "email", "sms", "in_app").
     * @param array<string,mixed>  $metadata
     */
    public function __construct(
        public int $recipientId,
        public string $subject,
        public string $body,
        public array $channels = ['in_app'],
        public array $metadata = [],
    ) {
        if ($this->recipientId <= 0) {
            throw new InvalidArgumentException('Invalid notification recipient ID.');
        }

        if (trim($this->subject) === '') {
            throw new InvalidArgumentException('Notification subject cannot be empty.');
        }

        if ($this->channels === []) {
            throw new InvalidArgumentException('Notification must have at least one delivery channel.');
        }
    }

    /**
     * Export the notification as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'recipient_id' => $this->recipientId,
            'subject' => $this->subject,
            'body' => $this->body,
            'channels' => $this->channels,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Reconstruct a notification from a previously exported array.
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['recipient_id'],
            $data['subject'],
            $data['body'],
            $data['channels'] ?? ['in_app'],
            $data['metadata'] ?? []
        );
    }
}
