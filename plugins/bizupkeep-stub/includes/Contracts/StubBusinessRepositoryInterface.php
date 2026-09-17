<?php

declare(strict_types=1);

namespace BizHub\Stub\Contracts;

use BizHub\Stub\Entities\StubBusiness;

/**
 * Persistence contract for the company-to-Stub-business link.
 *
 * @package BizHub\Stub\Contracts
 */
interface StubBusinessRepositoryInterface
{
    public function findByCompanyUuid(string $companyUuid): ?StubBusiness;

    public function save(StubBusiness $business): StubBusiness;

    /**
     * @return StubBusiness[]
     */
    public function findAll(): array;
}
