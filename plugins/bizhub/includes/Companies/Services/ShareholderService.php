<?php

declare(strict_types=1);

namespace BizHub\Companies\Services;

use BizHub\Companies\Contracts\CompanyRepositoryInterface;
use BizHub\Companies\Contracts\ShareholderRepositoryInterface;
use BizHub\Companies\DTO\ShareholderData;
use BizHub\Companies\Entities\Shareholder;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Companies\Exceptions\ShareholderNotFoundException;

/**
 * Implements the business operations for Shareholder management.
 *
 * Unlike Director (which can exist unassigned, then be attached to a
 * company later via assignToCompany()), Shareholder requires its
 * companyUuid at construction time - so, unlike
 * DirectorService::addDirectorToCompany(), there is no separate
 * assignment step here.
 *
 * @package BizHub\Companies\Services
 */
final class ShareholderService
{
    public function __construct(
        private readonly ShareholderRepositoryInterface $shareholders,
        private readonly CompanyRepositoryInterface $companies
    ) {
    }

    /**
     * Add a new shareholder to an existing company.
     */
    public function addShareholderToCompany(string $companyUuid, ShareholderData $shareholderData): Shareholder
    {
        $company = $this->companies->findByUuid($companyUuid)
            ?? throw CompanyNotFoundException::withUuid($companyUuid);

        $shareholder = new Shareholder(
            $shareholderData->uuid,
            $company->getUuid(),
            $shareholderData->fullName,
            $shareholderData->idNumber,
            $shareholderData->passportNumber,
            $shareholderData->sharesPercentage
        );

        $company->addShareholder($shareholder);

        return $this->shareholders->save($shareholder);
    }

    /**
     * Retrieve a shareholder by UUID.
     */
    public function getShareholder(string $uuid): Shareholder
    {
        return $this->shareholders->findByUuid($uuid)
            ?? throw ShareholderNotFoundException::withUuid($uuid);
    }

    /**
     * Retrieve every shareholder belonging to a company.
     *
     * @return Shareholder[]
     */
    public function getShareholdersForCompany(string $companyUuid): array
    {
        return $this->shareholders->findByCompanyUuid($companyUuid);
    }

    /**
     * Update a shareholder's details.
     */
    public function updateShareholder(ShareholderData $shareholderData): Shareholder
    {
        $shareholder = $this->getShareholder($shareholderData->uuid);

        $shareholder->setFullName($shareholderData->fullName);
        $shareholder->setSharesPercentage($shareholderData->sharesPercentage);

        return $this->shareholders->save($shareholder);
    }

    /**
     * Permanently remove a shareholder.
     */
    public function removeShareholder(string $uuid): void
    {
        $this->shareholders->delete($this->getShareholder($uuid));
    }
}
