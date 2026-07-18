<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\DTO;

use BizHub\ClientPortal\Entities\ClientStatus;

/**
 * Data Transfer Object representing a Client.
 *
 * @package BizHub\ClientPortal\DTO
 */
final readonly class ClientData
{
    public function __construct(
        public string $uuid,
        public int $wpUserId,
        public ProfileData $profile,
        public ClientStatus $status = ClientStatus::PENDING,
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
            'uuid' => $this->uuid,
            'wp_user_id' => $this->wpUserId,
            'profile' => $this->profile->toArray(),
            'status' => $this->status->value,
        ];
    }
}
