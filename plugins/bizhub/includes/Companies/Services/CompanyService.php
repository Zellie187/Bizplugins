<?php

declare(strict_types=1);

namespace BizHub\Companies\Services;

use BizHub\Companies\Contracts\CompanyRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\Director;
use BizHub\Companies\Entities\RegisteredAddress;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Companies\Exceptions\InvalidCompanyException;
use DateTimeImmutable;

/**
 * Implements the business operations for Company management.
 *
 * @package BizHub\Companies\Services
 */
final class CompanyService implements CompanyServiceInterface
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function createCompany(CompanyData $companyData): Company
    {
        if ($this->companies->exists($companyData->registrationNumber)) {
            throw InvalidCompanyException::duplicateRegistrationNumber(
                $companyData->registrationNumber
            );
        }

        $company = new Company(
            $companyData->uuid,
            $companyData->clientId,
            $companyData->registrationNumber,
            $companyData->companyName,
            $companyData->companyType,
            $companyData->status,
            $this->addressFromData($companyData),
            $companyData->incorporationDate,
            $companyData->createdAt ?? new DateTimeImmutable()
        );

        foreach ($companyData->directors as $directorData) {
            $company->addDirector(
                new Director(
                    $directorData->uuid,
                    $directorData->firstName,
                    $directorData->lastName,
                    $directorData->idNumber,
                    $directorData->passportNumber,
                    $directorData->appointmentDate,
                    $directorData->resignationDate,
                    $directorData->active
                )
            );
        }

        return $this->companies->save($company);
    }

    /**
     * {@inheritDoc}
     */
    public function updateCompany(CompanyData $companyData): Company
    {
        $company = $this->getCompany($companyData->uuid);

        if (
            $companyData->registrationNumber !== $company->getRegistrationNumber()
            && $this->companies->exists($companyData->registrationNumber)
        ) {
            throw InvalidCompanyException::duplicateRegistrationNumber(
                $companyData->registrationNumber
            );
        }

        $company->setRegistrationNumber($companyData->registrationNumber);
        $company->setCompanyName($companyData->companyName);
        $company->setCompanyType($companyData->companyType);
        $company->setStatus($companyData->status);
        $company->setRegisteredAddress($this->addressFromData($companyData));
        $company->setIncorporationDate($companyData->incorporationDate);

        return $this->companies->save($company);
    }

    /**
     * {@inheritDoc}
     */
    public function getCompany(string $uuid): Company
    {
        return $this->companies->findByUuid($uuid)
            ?? throw CompanyNotFoundException::withUuid($uuid);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteCompany(string $uuid): void
    {
        $this->companies->delete($this->getCompany($uuid));
    }

    /**
     * {@inheritDoc}
     */
    public function getCompaniesForClient(int $clientId): array
    {
        return $this->companies->findByClientId($clientId);
    }

    /**
     * {@inheritDoc}
     */
    public function getCompanySummaries(int $clientId): array
    {
        return $this->companies->findSummariesByClientId($clientId);
    }

    /**
     * Build a RegisteredAddress entity from a CompanyData DTO.
     */
    private function addressFromData(CompanyData $companyData): RegisteredAddress
    {
        $address = $companyData->registeredAddress;

        return new RegisteredAddress(
            $address->addressLine1,
            $address->addressLine2,
            $address->suburb,
            $address->city,
            $address->province,
            $address->postalCode,
            $address->country
        );
    }
}
