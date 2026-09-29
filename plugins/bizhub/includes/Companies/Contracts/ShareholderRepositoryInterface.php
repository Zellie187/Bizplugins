<?php

declare(strict_types=1);

namespace BizHub\Companies\Contracts;

use BizHub\Companies\Entities\Shareholder;

/**
 * Defines the persistence contract for Shareholder entities.
 *
 * @package BizHub\Companies\Contracts
 */
interface ShareholderRepositoryInterface
{
    /**
     * Find a shareholder by UUID.
     *
     * @param string $uuid
     *
     * @return Shareholder|null
     */
    public function findByUuid(string $uuid): ?Shareholder;

    /**
     * Retrieve shareholders belonging to a company.
     *
     * @param string $companyUuid
     *
     * @return Shareholder[]
     */
    public function findByCompanyUuid(
        string $companyUuid
    ): array;

    /**
     * Save a shareholder.
     *
     * @param Shareholder $shareholder
     *
     * @return Shareholder
     */
    public function save(
        Shareholder $shareholder
    ): Shareholder;

    /**
     * Delete a shareholder.
     *
     * @param Shareholder $shareholder
     *
     * @return void
     */
    public function delete(
        Shareholder $shareholder
    ): void;
}
