<?php

declare(strict_types=1);

namespace BizHub\Companies\DTO;

use BizHub\Companies\Entities\CompanyStatus;

/**
 * Lightweight Data Transfer Object representing a company summary.
 *
 * Intended for dashboards, listings and search results where the
 * complete company aggregate is not required.
 *
 * @package BizHub\Companies\DTO
 */
final readonly class CompanySummary
{
    /**
     * Create a new Company Summary DTO.
     *
     * @param string        $uuid
     * @param string        $registrationNumber
     * @param string        $companyName
     * @param CompanyStatus $status
     * @param int           $directorCount
     */
    public function __construct(
        public string $uuid,
        public string $registrationNumber,
        public string $companyName,
        public CompanyStatus $status,
        public int $directorCount = 0,
    ) {
    }

    /**
     * Determine whether the company is active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Export the DTO as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid'                => $this->uuid,
            'registration_number' => $this->registrationNumber,
            'company_name'        => $this->companyName,
            'status'              => $this->status->value,
            'status_label'        => $this->status->label(),
            'director_count'      => $this->directorCount,
        ];
    }
}
