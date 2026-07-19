<?php

declare(strict_types=1);

namespace BizHub\Companies\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a registered company within the BizHub domain.
 *
 * This entity is the aggregate root for company-related information.
 *
 * @package BizHub\Companies\Entities
 */
final class Company
{
    /**
     * @var Director[]
     */
    private array $directors = [];

    /**
     * Company constructor.
     *
     * @param string                 $uuid
     * @param int                    $clientId
     * @param string                 $registrationNumber
     * @param string                 $companyName
     * @param string                 $companyType
     * @param CompanyStatus          $status
     * @param RegisteredAddress      $registeredAddress
     * @param DateTimeImmutable|null $incorporationDate
     * @param DateTimeImmutable      $createdAt
     * @param DateTimeImmutable|null $updatedAt
     */
    public function __construct(
        private readonly string $uuid,
        private readonly int $clientId,
        private string $registrationNumber,
        private string $companyName,
        private string $companyType,
        private CompanyStatus $status,
        private RegisteredAddress $registeredAddress,
        private ?DateTimeImmutable $incorporationDate = null,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Validate entity state.
     *
     * @return void
     */
    private function validate(): void
    {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Company UUID cannot be empty.');
        }

        if ($this->clientId <= 0) {
            throw new InvalidArgumentException('Invalid client ID.');
        }

        if ($this->registrationNumber === '') {
            throw new InvalidArgumentException('Registration number cannot be empty.');
        }

        if ($this->companyName === '') {
            throw new InvalidArgumentException('Company name cannot be empty.');
        }

        if ($this->companyType === '') {
            throw new InvalidArgumentException('Company type cannot be empty.');
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
     * Get client ID.
     */
    public function getClientId(): int
    {
        return $this->clientId;
    }

    /**
     * Get registration number.
     */
    public function getRegistrationNumber(): string
    {
        return $this->registrationNumber;
    }

    /**
     * Update registration number.
     */
    public function setRegistrationNumber(string $registrationNumber): void
    {
        if ($registrationNumber === '') {
            throw new InvalidArgumentException('Registration number cannot be empty.');
        }

        $this->registrationNumber = $registrationNumber;
        $this->touch();
    }

    /**
     * Get company name.
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    /**
     * Update company name.
     */
    public function setCompanyName(string $companyName): void
    {
        if ($companyName === '') {
            throw new InvalidArgumentException('Company name cannot be empty.');
        }

        $this->companyName = $companyName;
        $this->touch();
    }

    /**
     * Get company type.
     */
    public function getCompanyType(): string
    {
        return $this->companyType;
    }

    /**
     * Update company type.
     */
    public function setCompanyType(string $companyType): void
    {
        if ($companyType === '') {
            throw new InvalidArgumentException('Company type cannot be empty.');
        }

        $this->companyType = $companyType;
        $this->touch();
    }

    /**
     * Get company status.
     */
    public function getStatus(): CompanyStatus
    {
        return $this->status;
    }

    /**
     * Update company status.
     */
    public function setStatus(CompanyStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    /**
     * Get registered address.
     */
    public function getRegisteredAddress(): RegisteredAddress
    {
        return $this->registeredAddress;
    }

    /**
     * Update registered address.
     */
    public function setRegisteredAddress(RegisteredAddress $registeredAddress): void
    {
        $this->registeredAddress = $registeredAddress;
        $this->touch();
    }

    /**
     * Get incorporation date.
     */
    public function getIncorporationDate(): ?DateTimeImmutable
    {
        return $this->incorporationDate;
    }

    /**
     * Set incorporation date.
     */
    public function setIncorporationDate(?DateTimeImmutable $incorporationDate): void
    {
        $this->incorporationDate = $incorporationDate;
        $this->touch();
    }

    /**
     * Add director.
     */
    public function addDirector(Director $director): void
    {
        foreach ($this->directors as $existingDirector) {
            if ($existingDirector->getUuid() === $director->getUuid()) {
                return;
            }
        }

        $director->assignToCompany($this->uuid);

        $this->directors[] = $director;
        $this->touch();
    }

    /**
     * Remove director by UUID.
     */
    public function removeDirector(string $uuid): void
    {
        $this->directors = array_values(
            array_filter(
                $this->directors,
                static fn (Director $director): bool => $director->getUuid() !== $uuid
            )
        );

        $this->touch();
    }

    /**
     * Get directors.
     *
     * @return Director[]
     */
    public function getDirectors(): array
    {
        return $this->directors;
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
     * Convert entity to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid'                 => $this->uuid,
            'client_id'            => $this->clientId,
            'registration_number'  => $this->registrationNumber,
            'company_name'         => $this->companyName,
            'company_type'         => $this->companyType,
            'status'               => $this->status->value,
            'registered_address'   => $this->registeredAddress->toArray(),
            'incorporation_date'   => $this->incorporationDate?->format('Y-m-d'),
            'created_at'           => $this->createdAt->format(DATE_ATOM),
            'updated_at'           => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
