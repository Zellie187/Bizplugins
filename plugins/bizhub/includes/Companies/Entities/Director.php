<?php

declare(strict_types=1);

namespace BizHub\Companies\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a company director.
 *
 * This entity contains the domain information for a director associated
 * with a company. It is persistence-agnostic and contains no WordPress
 * specific functionality.
 *
 * @package BizHub\Companies\Entities
 */
final class Director
{
    /**
     * Create a new director.
     *
     * @param string                 $uuid
     * @param string                 $firstName
     * @param string                 $lastName
     * @param string|null            $idNumber
     * @param string|null            $passportNumber
     * @param DateTimeImmutable      $appointmentDate
     * @param DateTimeImmutable|null $resignationDate
     * @param bool                   $active
     * @param string|null            $companyUuid
     * @param string|null            $phone
     * @param string|null            $email
     * @param RegisteredAddress|null $address
     */
    public function __construct(
        private readonly string $uuid,
        private string $firstName,
        private string $lastName,
        private ?string $idNumber,
        private ?string $passportNumber,
        private DateTimeImmutable $appointmentDate,
        private ?DateTimeImmutable $resignationDate = null,
        private bool $active = true,
        private ?string $companyUuid = null,
        private ?string $phone = null,
        private ?string $email = null,
        private ?RegisteredAddress $address = null
    ) {
        $this->validate();
    }

    /**
     * Validate the entity.
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException(
                'Director UUID cannot be empty.'
            );
        }

        if (trim($this->firstName) === '') {
            throw new InvalidArgumentException(
                'Director first name cannot be empty.'
            );
        }

        if (trim($this->lastName) === '') {
            throw new InvalidArgumentException(
                'Director last name cannot be empty.'
            );
        }

        if (
            empty($this->idNumber)
            && empty($this->passportNumber)
        ) {
            throw new InvalidArgumentException(
                'Either an ID number or passport number is required.'
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
        return trim(
            sprintf(
                '%s %s',
                $this->firstName,
                $this->lastName
            )
        );
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
     * Get appointment date.
     */
    public function getAppointmentDate(): DateTimeImmutable
    {
        return $this->appointmentDate;
    }

    /**
     * Get resignation date.
     */
    public function getResignationDate(): ?DateTimeImmutable
    {
        return $this->resignationDate;
    }

    /**
     * Determine whether the director is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Update first name.
     */
    public function setFirstName(string $firstName): void
    {
        if (trim($firstName) === '') {
            throw new InvalidArgumentException(
                'First name cannot be empty.'
            );
        }

        $this->firstName = $firstName;
    }

    /**
     * Update last name.
     */
    public function setLastName(string $lastName): void
    {
        if (trim($lastName) === '') {
            throw new InvalidArgumentException(
                'Last name cannot be empty.'
            );
        }

        $this->lastName = $lastName;
    }

    /**
     * Update ID number.
     */
    public function setIdNumber(?string $idNumber): void
    {
        $this->idNumber = $idNumber;
        $this->validate();
    }

    /**
     * Update passport number.
     */
    public function setPassportNumber(?string $passportNumber): void
    {
        $this->passportNumber = $passportNumber;
        $this->validate();
    }

    /**
     * Mark the director as resigned.
     */
    public function resign(
        DateTimeImmutable $resignationDate
    ): void {
        if ($resignationDate < $this->appointmentDate) {
            throw new InvalidArgumentException(
                'Resignation date cannot be before appointment date.'
            );
        }

        $this->resignationDate = $resignationDate;
        $this->active = false;
    }

    /**
     * Reactivate the director.
     */
    public function reactivate(): void
    {
        $this->resignationDate = null;
        $this->active = true;
    }

    /**
     * Get the UUID of the company this director belongs to.
     */
    public function getCompanyUuid(): ?string
    {
        return $this->companyUuid;
    }

    /**
     * Get contact phone number.
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * Update contact phone number.
     */
    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    /**
     * Get contact email address.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Update contact email address.
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * Get residential address.
     */
    public function getAddress(): ?RegisteredAddress
    {
        return $this->address;
    }

    /**
     * Update residential address.
     */
    public function setAddress(?RegisteredAddress $address): void
    {
        $this->address = $address;
    }

    /**
     * Associate the director with a company.
     */
    public function assignToCompany(string $companyUuid): void
    {
        $this->companyUuid = $companyUuid;
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
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'id_number' => $this->idNumber,
            'passport_number' => $this->passportNumber,
            'appointment_date' => $this->appointmentDate->format('Y-m-d'),
            'resignation_date' => $this->resignationDate?->format('Y-m-d'),
            'active' => $this->active,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address?->toArray(),
        ];
    }
}
