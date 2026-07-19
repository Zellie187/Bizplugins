<?php

declare(strict_types=1);

namespace BizHub\Companies\Entities;

use InvalidArgumentException;

/**
 * Represents a company shareholder.
 *
 * This entity is persistence-agnostic and contains no WordPress
 * specific functionality.
 *
 * @package BizHub\Companies\Entities
 */
final class Shareholder
{
    /**
     * @param string      $uuid
     * @param string      $companyUuid
     * @param string      $fullName
     * @param string|null $idNumber
     * @param string|null $passportNumber
     * @param float       $sharesPercentage Percentage of total shares held (0-100).
     */
    public function __construct(
        private readonly string $uuid,
        private readonly string $companyUuid,
        private string $fullName,
        private ?string $idNumber,
        private ?string $passportNumber,
        private float $sharesPercentage
    ) {
        $this->validate();
    }

    /**
     * Validate the entity.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Shareholder UUID cannot be empty.');
        }

        if ($this->companyUuid === '') {
            throw new InvalidArgumentException('Shareholder must be associated with a company.');
        }

        if (trim($this->fullName) === '') {
            throw new InvalidArgumentException('Shareholder full name cannot be empty.');
        }

        if (empty($this->idNumber) && empty($this->passportNumber)) {
            throw new InvalidArgumentException(
                'Either an ID number or passport number is required.'
            );
        }

        if ($this->sharesPercentage < 0 || $this->sharesPercentage > 100) {
            throw new InvalidArgumentException(
                'Shares percentage must be between 0 and 100.'
            );
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
     * Get the UUID of the company this shareholder belongs to.
     */
    public function getCompanyUuid(): string
    {
        return $this->companyUuid;
    }

    /**
     * Get full name.
     */
    public function getFullName(): string
    {
        return $this->fullName;
    }

    /**
     * Update full name.
     */
    public function setFullName(string $fullName): void
    {
        if (trim($fullName) === '') {
            throw new InvalidArgumentException('Full name cannot be empty.');
        }

        $this->fullName = $fullName;
    }

    /**
     * Get ID number.
     */
    public function getIdNumber(): ?string
    {
        return $this->idNumber;
    }

    /**
     * Get passport number.
     */
    public function getPassportNumber(): ?string
    {
        return $this->passportNumber;
    }

    /**
     * Get shares percentage.
     */
    public function getSharesPercentage(): float
    {
        return $this->sharesPercentage;
    }

    /**
     * Update shares percentage.
     */
    public function setSharesPercentage(float $sharesPercentage): void
    {
        if ($sharesPercentage < 0 || $sharesPercentage > 100) {
            throw new InvalidArgumentException(
                'Shares percentage must be between 0 and 100.'
            );
        }

        $this->sharesPercentage = $sharesPercentage;
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
            'company_uuid' => $this->companyUuid,
            'full_name' => $this->fullName,
            'id_number' => $this->idNumber,
            'passport_number' => $this->passportNumber,
            'shares_percentage' => $this->sharesPercentage,
        ];
    }
}
