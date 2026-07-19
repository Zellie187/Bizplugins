<?php

declare(strict_types=1);

namespace BizHub\Companies\DTO;

use BizHub\Companies\Entities\CompanyStatus;
use DateTimeImmutable;

/**
 * Data Transfer Object representing a Company.
 *
 * This DTO is used to transfer company data between the
 * repository, service and presentation layers.
 *
 * @package BizHub\Companies\DTO
 */
final readonly class CompanyData
{
    /**
     * Create a new Company DTO.
     *
     * @param string                 $uuid
     * @param int                    $clientId
     * @param string                 $registrationNumber
     * @param string                 $companyName
     * @param string                 $companyType
     * @param CompanyStatus          $status
     * @param AddressData            $registeredAddress
     * @param DirectorData[]         $directors
     * @param DateTimeImmutable|null $incorporationDate
     * @param DateTimeImmutable|null $createdAt
     * @param DateTimeImmutable|null $updatedAt
     */
    public function __construct(
        public string $uuid,
        public int $clientId,
        public string $registrationNumber,
        public string $companyName,
        public string $companyType,
        public CompanyStatus $status,
        public AddressData $registeredAddress,
        public array $directors = [],
        public ?DateTimeImmutable $incorporationDate = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
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
            'client_id' => $this->clientId,
            'registration_number' => $this->registrationNumber,
            'company_name' => $this->companyName,
            'company_type' => $this->companyType,
            'status' => $this->status->value,
            'registered_address' => $this->registeredAddress->toArray(),
            'directors' => array_map(
                static fn (DirectorData $director): array => $director->toArray(),
                $this->directors
            ),
            'incorporation_date' => $this->incorporationDate?->format('Y-m-d'),
            'created_at' => $this->createdAt?->format(DATE_ATOM),
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
