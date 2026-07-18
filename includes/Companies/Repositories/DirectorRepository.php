<?php

declare(strict_types=1);

namespace BizHub\Companies\Repositories;

use BizHub\Companies\Contracts\DirectorRepositoryInterface;
use BizHub\Companies\Entities\Director;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * Persists Director entities using the framework database abstraction.
 *
 * @package BizHub\Companies\Repositories
 */
final class DirectorRepository implements DirectorRepositoryInterface
{
    private const TABLE = 'bizhub_directors';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Director
    {
        $row = $this->database->findOne(self::TABLE, ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?Director
    {
        $row = $this->database->findOne(self::TABLE, ['uuid' => $uuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByCompanyUuid(string $companyUuid): array
    {
        $rows = $this->database->findAll(
            self::TABLE,
            ['company_uuid' => $companyUuid],
            ['last_name' => 'ASC']
        );

        return array_map(
            fn (array $row): Director => $this->hydrate($row),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save(Director $director): Director
    {
        $data = $this->dehydrate($director);

        if ($this->database->exists(self::TABLE, ['uuid' => $director->getUuid()])) {
            $this->database->update(self::TABLE, $data, ['uuid' => $director->getUuid()]);
        } else {
            $this->database->insert(self::TABLE, $data);
        }

        return $director;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Director $director): void
    {
        $this->database->delete(self::TABLE, ['uuid' => $director->getUuid()]);
    }

    /**
     * Hydrate a database row into a Director entity.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Director
    {
        return new Director(
            $row['uuid'],
            $row['first_name'],
            $row['last_name'],
            $row['id_number'] ?? null,
            $row['passport_number'] ?? null,
            new DateTimeImmutable((string) $row['appointment_date']),
            empty($row['resignation_date']) ? null : new DateTimeImmutable((string) $row['resignation_date']),
            (bool) $row['active'],
            $row['company_uuid'] ?? null
        );
    }

    /**
     * Convert a Director entity into a database row.
     *
     * @return array<string,mixed>
     */
    private function dehydrate(Director $director): array
    {
        return [
            'uuid' => $director->getUuid(),
            'company_uuid' => $director->getCompanyUuid(),
            'first_name' => $director->getFirstName(),
            'last_name' => $director->getLastName(),
            'id_number' => $director->getIdNumber(),
            'passport_number' => $director->getPassportNumber(),
            'appointment_date' => $director->getAppointmentDate()->format('Y-m-d'),
            'resignation_date' => $director->getResignationDate()?->format('Y-m-d'),
            'active' => $director->isActive() ? 1 : 0,
        ];
    }
}
