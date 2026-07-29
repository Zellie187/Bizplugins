<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\ImportMapping;

/**
 * Persistence contract for a company's saved bank-statement column
 * mapping. The only class allowed to touch DatabaseInterface for this
 * table.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface ImportMappingRepositoryInterface
{
    public function findByCompanyUuid(string $companyUuid): ?ImportMapping;

    public function save(ImportMapping $mapping): ImportMapping;
}
