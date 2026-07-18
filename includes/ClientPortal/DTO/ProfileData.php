<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\DTO;

/**
 * Data Transfer Object representing a client profile.
 *
 * @package BizHub\ClientPortal\DTO
 */
final readonly class ProfileData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $phone = '',
        public ?string $avatarUrl = null,
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
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}
