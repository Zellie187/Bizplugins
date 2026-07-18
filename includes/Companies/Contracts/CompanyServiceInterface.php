<?php

declare(strict_types=1);

namespace BizHub\Companies\Contracts;

use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\DTO\CompanySummary;
use BizHub\Companies\Entities\Company;

/**
 * Defines the business operations for Company management.
 *
 * Services implement validation, authorization and business rules.
 * Persistence is delegated to repositories.
 *
 * @package BizHub\Companies\Contracts
 */
interface CompanyServiceInterface
{
    /**
     * Create a company.
     *
     * @param CompanyData $companyData
     *
     * @return Company
     */
    public function createCompany(
        CompanyData $companyData
    ): Company;

    /**
     * Update a company.
     *
     * @param CompanyData $companyData
     *
     * @return Company
     */
    public function updateCompany(
        CompanyData $companyData
    ): Company;

    /**
     * Retrieve a company.
     *
     * @param string $uuid
     *
     * @return Company
     */
    public function getCompany(
        string $uuid
    ): Company;

    /**
     * Delete a company.
     *
     * @param string $uuid
     *
     * @return void
     */
    public function deleteCompany(
        string $uuid
    ): void;

    /**
     * Retrieve all companies for a client.
     *
     * @param int $clientId
     *
     * @return Company[]
     */
    public function getCompaniesForClient(
        int $clientId
    ): array;

    /**
     * Retrieve company summaries.
     *
     * @param int $clientId
     *
     * @return CompanySummary[]
     */
    public function getCompanySummaries(
        int $clientId
    ): array;
}
