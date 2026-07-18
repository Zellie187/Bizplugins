<?php

declare(strict_types=1);

namespace BizHub\Companies\Services;

use BizHub\Companies\Contracts\CompanyRepositoryInterface;
use BizHub\Companies\Contracts\DirectorRepositoryInterface;
use BizHub\Companies\DTO\DirectorData;
use BizHub\Companies\Entities\Director;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Companies\Exceptions\DirectorNotFoundException;
use DateTimeImmutable;

/**
 * Implements the business operations for Director management.
 *
 * @package BizHub\Companies\Services
 */
final class DirectorService
{
    public function __construct(
        private readonly DirectorRepositoryInterface $directors,
        private readonly CompanyRepositoryInterface $companies
    ) {
    }

    /**
     * Add a new director to an existing company.
     */
    public function addDirectorToCompany(string $companyUuid, DirectorData $directorData): Director
    {
        $company = $this->companies->findByUuid($companyUuid)
            ?? throw CompanyNotFoundException::withUuid($companyUuid);

        $director = new Director(
            $directorData->uuid,
            $directorData->firstName,
            $directorData->lastName,
            $directorData->idNumber,
            $directorData->passportNumber,
            $directorData->appointmentDate,
            $directorData->resignationDate,
            $directorData->active
        );

        $company->addDirector($director);

        return $this->directors->save($director);
    }

    /**
     * Retrieve a director by UUID.
     */
    public function getDirector(string $uuid): Director
    {
        return $this->directors->findByUuid($uuid)
            ?? throw DirectorNotFoundException::withUuid($uuid);
    }

    /**
     * Retrieve every director belonging to a company.
     *
     * @return Director[]
     */
    public function getDirectorsForCompany(string $companyUuid): array
    {
        return $this->directors->findByCompanyUuid($companyUuid);
    }

    /**
     * Update a director's personal details.
     */
    public function updateDirector(DirectorData $directorData): Director
    {
        $director = $this->getDirector($directorData->uuid);

        $director->setFirstName($directorData->firstName);
        $director->setLastName($directorData->lastName);
        $director->setIdNumber($directorData->idNumber);
        $director->setPassportNumber($directorData->passportNumber);

        return $this->directors->save($director);
    }

    /**
     * Mark a director as resigned.
     */
    public function resignDirector(string $uuid, DateTimeImmutable $resignationDate): Director
    {
        $director = $this->getDirector($uuid);

        $director->resign($resignationDate);

        return $this->directors->save($director);
    }

    /**
     * Reactivate a previously resigned director.
     */
    public function reactivateDirector(string $uuid): Director
    {
        $director = $this->getDirector($uuid);

        $director->reactivate();

        return $this->directors->save($director);
    }

    /**
     * Permanently remove a director.
     */
    public function removeDirector(string $uuid): void
    {
        $this->directors->delete($this->getDirector($uuid));
    }
}
