<?php

declare(strict_types=1);

namespace BizHub\Companies\Repositories;

use BizHub\Companies\Contracts\ShareholderRepositoryInterface;
use BizHub\Companies\Entities\Shareholder;
use BizHub\Framework\Database\Contracts\DatabaseInterface;

/**
 * Persists Shareholder entities using the framework database
 * abstraction.
 *
 * @package BizHub\Companies\Repositories
 */
final class ShareholderRepository implements ShareholderRepositoryInterface
{
    private const TABLE = 'bizhub_shareholders';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?Shareholder
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
            ['full_name' => 'ASC']
        );

        return array_map(
            fn (array $row): Shareholder => $this->hydrate($row),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save(Shareholder $shareholder): Shareholder
    {
        $data = $this->dehydrate($shareholder);

        if ($this->database->exists(self::TABLE, ['uuid' => $shareholder->getUuid()])) {
            $this->database->update(self::TABLE, $data, ['uuid' => $shareholder->getUuid()]);
        } else {
            $this->database->insert(self::TABLE, $data);
        }

        return $shareholder;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Shareholder $shareholder): void
    {
        $this->database->delete(self::TABLE, ['uuid' => $shareholder->getUuid()]);
    }

    /**
     * Hydrate a database row into a Shareholder entity.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Shareholder
    {
        return new Shareholder(
            (string) $row['uuid'],
            (string) $row['company_uuid'],
            (string) $row['full_name'],
            empty($row['id_number']) ? null : (string) $row['id_number'],
            empty($row['passport_number']) ? null : (string) $row['passport_number'],
            (float) $row['shares_percentage']
        );
    }

    /**
     * Convert a Shareholder entity into a database row.
     *
     * @return array<string,mixed>
     */
    private function dehydrate(Shareholder $shareholder): array
    {
        return [
            'uuid' => $shareholder->getUuid(),
            'company_uuid' => $shareholder->getCompanyUuid(),
            'full_name' => $shareholder->getFullName(),
            'id_number' => $shareholder->getIdNumber(),
            'passport_number' => $shareholder->getPassportNumber(),
            'shares_percentage' => $shareholder->getSharesPercentage(),
        ];
    }
}
