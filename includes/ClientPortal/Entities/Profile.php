<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Entities;

use InvalidArgumentException;

/**
 * Represents a client's personal profile details.
 *
 * @package BizHub\ClientPortal\Entities
 */
final class Profile
{
    public function __construct(
        private string $firstName,
        private string $lastName,
        private string $phone = '',
        private ?string $avatarUrl = null
    ) {
        $this->validate();
    }

    /**
     * Validate the entity.
     */
    private function validate(): void
    {
        if (trim($this->firstName) === '') {
            throw new InvalidArgumentException('First name cannot be empty.');
        }

        if (trim($this->lastName) === '') {
            throw new InvalidArgumentException('Last name cannot be empty.');
        }
    }

    /**
     * Get first name.
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * Get last name.
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Get full name.
     */
    public function getFullName(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }

    /**
     * Get phone number.
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Get avatar URL.
     */
    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    /**
     * Update the profile's contact details.
     */
    public function update(string $firstName, string $lastName, string $phone, ?string $avatarUrl): void
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
        $this->avatarUrl = $avatarUrl;

        $this->validate();
    }

    /**
     * Export entity as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}
