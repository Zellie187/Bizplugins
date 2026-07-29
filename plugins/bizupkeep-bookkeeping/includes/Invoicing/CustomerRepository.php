<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Invoicing;

use BizHub\Bookkeeping\Contracts\CustomerRepositoryInterface;
use BizHub\Bookkeeping\Entities\Customer;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the customers table.
 *
 * @package BizHub\Bookkeeping\Invoicing
 */
final class CustomerRepository implements CustomerRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_customers';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid): array
    {
        $rows = $this->database->findAll(self::TABLE, ['company_uuid' => $companyUuid], ['name' => 'ASC']);

        return array_map($this->hydrate(...), $rows);
    }

    public function findByUuid(string $uuid): ?Customer
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function save(Customer $customer): Customer
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $customer->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($customer), ['uuid' => $customer->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($customer));
        }

        return $customer;
    }

    public function delete(string $uuid): void
    {
        $this->database->delete(self::TABLE, ['uuid' => $uuid]);
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(Customer $customer): array
    {
        return [
            'uuid' => $customer->uuid,
            'company_uuid' => $customer->companyUuid,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address_line_1' => $customer->addressLine1,
            'address_line_2' => $customer->addressLine2,
            'suburb' => $customer->suburb,
            'city' => $customer->city,
            'province' => $customer->province,
            'postal_code' => $customer->postalCode,
            'created_at' => $customer->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $customer->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Customer
    {
        return new Customer(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            phone: (string) $row['phone'],
            addressLine1: (string) $row['address_line_1'],
            addressLine2: (string) $row['address_line_2'],
            suburb: (string) $row['suburb'],
            city: (string) $row['city'],
            province: (string) $row['province'],
            postalCode: (string) $row['postal_code'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
        );
    }
}
