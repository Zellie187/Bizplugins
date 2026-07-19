<?php

declare(strict_types=1);

namespace BizHub\Companies\Services;

use BizHub\Companies\Contracts\CompanyRepositoryInterface;
use BizHub\Companies\DTO\CompanySummary;
use BizHub\Companies\Entities\Company;
use BizHub\Framework\Support\Str;

/**
 * Read-only lookup operations for Companies.
 *
 * Kept separate from CompanyService so that simple, cacheable read
 * operations (dashboards, autocomplete, validation checks) do not
 * depend on the mutation-focused service.
 *
 * @package BizHub\Companies\Services
 */
final class CompanyLookupService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies
    ) {
    }

    /**
     * Find a company by registration number.
     */
    public function findByRegistrationNumber(string $registrationNumber): ?Company
    {
        return $this->companies->findByRegistrationNumber($registrationNumber);
    }

    /**
     * Determine whether a registration number is already in use.
     */
    public function existsByRegistrationNumber(string $registrationNumber): bool
    {
        return $this->companies->exists($registrationNumber);
    }

    /**
     * Find a company by UUID.
     */
    public function findByUuid(string $uuid): ?Company
    {
        return $this->companies->findByUuid($uuid);
    }

    /**
     * Search a client's company summaries by name or registration number.
     *
     * @return CompanySummary[]
     */
    public function search(int $clientId, string $term): array
    {
        $term = trim($term);

        $summaries = $this->companies->findSummariesByClientId($clientId);

        if ($term === '') {
            return $summaries;
        }

        return array_values(array_filter(
            $summaries,
            static fn (CompanySummary $summary): bool =>
                Str::contains(mb_strtolower($summary->companyName), mb_strtolower($term))
                || Str::contains(mb_strtolower($summary->registrationNumber), mb_strtolower($term))
        ));
    }
}
