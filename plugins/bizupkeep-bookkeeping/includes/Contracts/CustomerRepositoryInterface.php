<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\Customer;

/**
 * Persistence contract for a company's own customers. The only class
 * allowed to touch DatabaseInterface for this table.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface CustomerRepositoryInterface
{
    /**
     * @return Customer[]
     */
    public function findByCompanyUuid(string $companyUuid): array;

    public function findByUuid(string $uuid): ?Customer;

    public function save(Customer $customer): Customer;

    public function delete(string $uuid): void;
}
