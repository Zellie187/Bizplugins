<?php

declare(strict_types=1);

namespace BizHub\Companies\Repositories;

use BizHub\Companies\Contracts\CompanyRepositoryInterface;
use BizHub\Companies\Contracts\DirectorRepositoryInterface;
use BizHub\Companies\DTO\CompanySummary;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Entities\RegisteredAddress;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * Persists Company aggregates using the framework database abstraction.
 *
 * @package BizHub\Companies\Repositories
 */
final class CompanyRepository implements CompanyRepositoryInterface
{
    private const TABLE = 'bizhub_companies';

    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly DirectorRepositoryInterface $directors
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Company
    {
        $row = $this->database->findOne(self::TABLE, ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?Company
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByRegistrationNumber(string $registrationNumber): ?Company
    {
        $row = $this->database->findOne(self::TABLE, ['registration_number' => $registrationNumber]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByClientId(int $clientId): array
    {
        $rows = $this->database->findAll(
            self::TABLE,
            ['client_id' => $clientId],
            ['company_name' => 'ASC']
        );

        return array_map(
            fn (array $row): Company => $this->hydrate($row),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findSummariesByClientId(int $clientId): array
    {
        $rows = $this->database->findAll(
            self::TABLE,
            ['client_id' => $clientId],
            ['company_name' => 'ASC']
        );

        return array_map(
            fn (array $row): CompanySummary => new CompanySummary(
                $row['uuid'],
                $row['registration_number'],
                $row['company_name'],
                CompanyStatus::from($row['status']),
                count($this->directors->findByCompanyUuid($row['uuid']))
            ),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $registrationNumber): bool
    {
        return $this->database->exists(self::TABLE, ['registration_number' => $registrationNumber]);
    }

    /**
     * {@inheritDoc}
     */
    public function save(Company $company): Company
    {
        $data = $this->dehydrate($company);

        if ($this->database->exists(self::TABLE, ['uuid' => $company->getUuid()])) {
            $this->database->update(self::TABLE, $data, ['uuid' => $company->getUuid()]);
        } else {
            $this->database->insert(self::TABLE, $data);
        }

        foreach ($company->getDirectors() as $director) {
            $this->directors->save($director);
        }

        return $company;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Company $company): void
    {
        foreach ($company->getDirectors() as $director) {
            $this->directors->delete($director);
        }

        $this->database->delete(self::TABLE, ['uuid' => $company->getUuid()]);
    }

    /**
     * Hydrate a database row into a Company aggregate, including its
     * registered address and associated directors.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Company
    {
        $address = new RegisteredAddress(
            $row['address_line_1'],
            $row['address_line_2'] ?? '',
            $row['suburb'] ?? '',
            $row['city'],
            $row['province'] ?? '',
            $row['postal_code'],
            $row['country'] ?? 'South Africa'
        );

        $company = new Company(
            $row['uuid'],
            (int) $row['client_id'],
            $row['registration_number'],
            $row['company_name'],
            $row['company_type'],
            CompanyStatus::from($row['status']),
            $address,
            $this->toDate($row['incorporation_date'] ?? null),
            $this->toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            $this->toDate($row['updated_at'] ?? null)
        );

        foreach ($this->directors->findByCompanyUuid($row['uuid']) as $director) {
            $company->addDirector($director);
        }

        return $company;
    }

    /**
     * Convert a Company aggregate into a database row.
     *
     * @return array<string,mixed>
     */
    private function dehydrate(Company $company): array
    {
        $address = $company->getRegisteredAddress();

        return [
            'uuid' => $company->getUuid(),
            'client_id' => $company->getClientId(),
            'registration_number' => $company->getRegistrationNumber(),
            'company_name' => $company->getCompanyName(),
            'company_type' => $company->getCompanyType(),
            'status' => $company->getStatus()->value,
            'address_line_1' => $address->getAddressLine1(),
            'address_line_2' => $address->getAddressLine2(),
            'suburb' => $address->getSuburb(),
            'city' => $address->getCity(),
            'province' => $address->getProvince(),
            'postal_code' => $address->getPostalCode(),
            'country' => $address->getCountry(),
            'incorporation_date' => $company->getIncorporationDate()?->format('Y-m-d'),
            'created_at' => $company->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $company->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse a nullable date column into a DateTimeImmutable.
     */
    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }
}
