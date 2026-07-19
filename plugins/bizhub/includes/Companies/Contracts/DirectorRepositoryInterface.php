<?php

declare(strict_types=1);

namespace BizHub\Companies\Contracts;

use BizHub\Companies\Entities\Director;

/**
 * Defines the persistence contract for Director entities.
 *
 * @package BizHub\Companies\Contracts
 */
interface DirectorRepositoryInterface
{
    /**
     * Find a director by ID.
     *
     * @param int $id
     *
     * @return Director|null
     */
    public function find(int $id): ?Director;

    /**
     * Find a director by UUID.
     *
     * @param string $uuid
     *
     * @return Director|null
     */
    public function findByUuid(string $uuid): ?Director;

    /**
     * Retrieve directors belonging to a company.
     *
     * @param string $companyUuid
     *
     * @return Director[]
     */
    public function findByCompanyUuid(
        string $companyUuid
    ): array;

    /**
     * Save a director.
     *
     * @param Director $director
     *
     * @return Director
     */
    public function save(
        Director $director
    ): Director;

    /**
     * Delete a director.
     *
     * @param Director $director
     *
     * @return void
     */
    public function delete(
        Director $director
    ): void;
}
