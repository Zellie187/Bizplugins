<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a portal client account.
 *
 * Each client is linked to exactly one WordPress user account.
 *
 * @package BizHub\ClientPortal\Entities
 */
final class Client
{
    public function __construct(
        private readonly string $uuid,
        private readonly int $wpUserId,
        private Profile $profile,
        private ClientStatus $status = ClientStatus::PENDING,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Validate entity state.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Client UUID cannot be empty.');
        }

        if ($this->wpUserId <= 0) {
            throw new InvalidArgumentException('Invalid WordPress user ID.');
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
     * Get the associated WordPress user ID.
     */
    public function getWpUserId(): int
    {
        return $this->wpUserId;
    }

    /**
     * Get profile.
     */
    public function getProfile(): Profile
    {
        return $this->profile;
    }

    /**
     * Replace the client's profile.
     */
    public function setProfile(Profile $profile): void
    {
        $this->profile = $profile;
        $this->touch();
    }

    /**
     * Get status.
     */
    public function getStatus(): ClientStatus
    {
        return $this->status;
    }

    /**
     * Update status.
     */
    public function setStatus(ClientStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    /**
     * Determine whether the client may access the portal.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Get creation timestamp.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get last update timestamp.
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Update modification timestamp.
     */
    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
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
            'wp_user_id' => $this->wpUserId,
            'profile' => $this->profile->toArray(),
            'status' => $this->status->value,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
