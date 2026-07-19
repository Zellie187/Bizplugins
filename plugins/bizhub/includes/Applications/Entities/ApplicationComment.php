<?php

declare(strict_types=1);

namespace BizHub\Applications\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a single comment left on an application.
 *
 * @package BizHub\Applications\Entities
 */
final readonly class ApplicationComment
{
    public function __construct(
        public string $uuid,
        public string $applicationUuid,
        public int $authorId,
        public string $message,
        public DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Comment UUID cannot be empty.');
        }

        if ($this->applicationUuid === '') {
            throw new InvalidArgumentException('Comment must be associated with an application.');
        }

        if (trim($this->message) === '') {
            throw new InvalidArgumentException('Comment message cannot be empty.');
        }
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
            'application_uuid' => $this->applicationUuid,
            'author_id' => $this->authorId,
            'message' => $this->message,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
