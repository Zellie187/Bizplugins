<?php

declare(strict_types=1);

namespace BizHub\Companies\Contracts;

use BizHub\Companies\DTO\CompanySummary;
use BizHub\Companies\Entities\Company;

/**
 * Defines the persistence contract for Company entities.
 *
 * Repository implementations are responsible only for data persistence.
 * Business rules belong in the service layer.
 *
 * @package BizHub\Companies\Contracts
 */
interface CompanyRepositoryInterface
{
    /**
     * Find a company by its internal database identifier.
     *
     * @param int $id Database identifier.
     *
     * @return Company|null
     */
    public function find(int $id): ?Company;

    /**
     * Find a company by its UUID.
     *
     * @param string $uuid Company UUID.
     *
     * @return Company|null
     */
    public function findByUuid(string $uuid): ?Company;

    /**
     * Find a company by registration number.
     *
     * @param string $registrationNumber Registration number.
     *
     * @return Company|null
     */
    public function findByRegistrationNumber(
        string $registrationNumber
    ): ?Company;

    /**
     * Retrieve all companies belonging to a client.
     *
     * @param int $clientId Client identifier.
     *
     * @return Company[]
     */
    public function findByClientId(int $clientId): array;

    /**
     * Retrieve lightweight company summaries for a client.
     *
     * @param int $clientId Client identifier.
     *
     * @return CompanySummary[]
     */
    public function findSummariesByClientId(
        int $clientId
    ): array;

    /**
     * Determine whether a company exists.
     *
     * @param string $registrationNumber Registration number.
     *
     * @return bool
     */
    public function exists(
        string $registrationNumber
    ): bool;

    /**
     * Persist a company.
     *
     * Creates or updates depending on implementation.
     *
     * @param Company $company Company entity.
     *
     * @return Company
     */
    public function save(
        Company $company
    ): Company;

    /**
     * Delete a company.
     *
     * @param Company $company Company entity.
     *
     * @return void
     */
    public function delete(
        Company $company
    ): void;
}
