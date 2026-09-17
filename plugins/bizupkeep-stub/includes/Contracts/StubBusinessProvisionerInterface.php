<?php

declare(strict_types=1);

namespace BizHub\Stub\Contracts;

use BizHub\ClientPortal\Entities\Client;
use BizHub\Companies\Entities\Company;

/**
 * Finds or creates the Stub business for a BizHub Company - the
 * find-or-create shape mirrors bizupkeep-payments'
 * CustomerProvisionerInterface exactly.
 *
 * @package BizHub\Stub\Contracts
 */
interface StubBusinessProvisionerInterface
{
    /**
     * Returns the existing Stub business uid for this Company, or
     * provisions a new one (via StubApiClientInterface::pushBusiness())
     * and persists the link on first call.
     */
    public function findOrCreate(Client $client, Company $company): string;
}
