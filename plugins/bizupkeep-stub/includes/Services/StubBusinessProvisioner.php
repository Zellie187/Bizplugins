<?php

declare(strict_types=1);

namespace BizHub\Stub\Services;

use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\ClientPortal\Entities\Client;
use BizHub\Companies\Entities\Company;
use BizHub\Stub\Contracts\StubApiClientInterface;
use BizHub\Stub\Contracts\StubBusinessProvisionerInterface;
use BizHub\Stub\Contracts\StubBusinessRepositoryInterface;
use BizHub\Stub\Entities\StubBusiness;
use BizHub\Stub\Exceptions\StubException;
use DateTimeImmutable;

/**
 * Finds or creates the Stub business for a Company - mirrors
 * bizupkeep-payments' CustomerProvisioner::findOrCreate() shape
 * exactly (local lookup first, external create only on miss).
 *
 * "industry" has no source field anywhere in Company/CompanySettings
 * today - deliberately defaulted to a fixed placeholder rather than
 * guessed, until/unless a real field gets added. "region" uses the
 * company's registered-address province, which is an approximation
 * (Stub's own definition of "region" isn't documented) rather than a
 * confirmed exact mapping.
 *
 * @package BizHub\Stub\Services
 */
final class StubBusinessProvisioner implements StubBusinessProvisionerInterface
{
    private const DEFAULT_INDUSTRY = 'Professional Services';

    public function __construct(
        private readonly StubBusinessRepositoryInterface $businesses,
        private readonly StubApiClientInterface $api,
        private readonly CompanySettingsRepositoryInterface $companySettings
    ) {
    }

    public function findOrCreate(Client $client, Company $company): string
    {
        $existing = $this->businesses->findByCompanyUuid($company->getUuid());

        if ($existing !== null) {
            return $existing->stubBusinessUid;
        }

        $wpUser = get_userdata($client->getWpUserId());

        if ($wpUser === false || $wpUser->user_email === '') {
            throw new StubException('Could not resolve an email address for this client.');
        }

        $settings = $this->companySettings->findByCompanyUuid($company->getUuid());
        $address = $company->getRegisteredAddress();

        $response = $this->api->pushBusiness([
            'businessname' => $company->getCompanyName(),
            'firstname' => $wpUser->first_name !== '' ? $wpUser->first_name : $wpUser->display_name,
            'lastname' => $wpUser->last_name,
            'email' => $wpUser->user_email,
            'phone' => $client->getProfile()->getPhone(),
            'industry' => self::DEFAULT_INDUSTRY,
            'region' => $address->getProvince(),
            'vatregistered' => $settings !== null && $settings->isVatRegistered,
        ]);

        $business = new StubBusiness(
            companyUuid: $company->getUuid(),
            stubBusinessUid: $response['uid'],
            createdAt: new DateTimeImmutable()
        );

        $this->businesses->save($business);

        return $business->stubBusinessUid;
    }
}
