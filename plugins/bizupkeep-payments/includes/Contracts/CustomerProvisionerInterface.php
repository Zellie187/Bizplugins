<?php

declare(strict_types=1);

namespace BizHub\Payments\Contracts;

use BizHub\ClientPortal\Entities\Client;
use BizHub\Companies\Entities\Company;

/**
 * Resolves the BizUpKeep Bookkeeping Customer (under A2Z's Internal
 * Books company) representing a given BizHub client/company, creating
 * one on first purchase. A client never creates or sees this record
 * directly - it exists only so A2Z's own invoices have someone to
 * bill.
 *
 * @package BizHub\Payments\Contracts
 */
interface CustomerProvisionerInterface
{
    /**
     * @return string The Bookkeeping Customer's uuid.
     */
    public function findOrCreate(string $internalCompanyUuid, Client $client, Company $company): string;
}
