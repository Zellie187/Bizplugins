<?php

declare(strict_types=1);

namespace BizHub\Payments\Services;

use BizHub\Bookkeeping\Contracts\InvoiceServiceInterface;
use BizHub\ClientPortal\Entities\Client;
use BizHub\Companies\Entities\Company;
use BizHub\Payments\Contracts\CustomerProvisionerInterface;
use BizHub\Payments\Exceptions\ValidationException;

/**
 * @package BizHub\Payments\Services
 */
final class CustomerProvisioner implements CustomerProvisionerInterface
{
    public function __construct(
        private readonly InvoiceServiceInterface $invoicing
    ) {
    }

    public function findOrCreate(string $internalCompanyUuid, Client $client, Company $company): string
    {
        $email = $this->clientEmail($client);

        foreach ($this->invoicing->listCustomers($internalCompanyUuid) as $customer) {
            if (strtolower($customer->email) === strtolower($email)) {
                return $customer->uuid;
            }
        }

        $address = $company->getRegisteredAddress();

        $created = $this->invoicing->createCustomer(
            $internalCompanyUuid,
            $company->getCompanyName(),
            $email,
            $client->getProfile()->getPhone(),
            $address->getAddressLine1(),
            $address->getAddressLine2(),
            $address->getSuburb(),
            $address->getCity(),
            $address->getProvince(),
            $address->getPostalCode(),
        );

        return $created->uuid;
    }

    private function clientEmail(Client $client): string
    {
        $wpUser = get_userdata($client->getWpUserId());

        if ($wpUser === false || $wpUser->user_email === '') {
            throw new ValidationException('Could not resolve an email address for this client.');
        }

        return $wpUser->user_email;
    }
}
