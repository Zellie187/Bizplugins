<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Accounts;

use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Entities\CompanySettings;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the company settings
 * table.
 *
 * @package BizHub\Bookkeeping\Accounts
 */
final class CompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_company_settings';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid): ?CompanySettings
    {
        $row = $this->database->findOne(self::TABLE, ['company_uuid' => $companyUuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function save(CompanySettings $settings): CompanySettings
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $settings->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($settings), ['uuid' => $settings->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($settings));
        }

        return $settings;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(CompanySettings $settings): array
    {
        return [
            'uuid' => $settings->uuid,
            'company_uuid' => $settings->companyUuid,
            'is_vat_registered' => $settings->isVatRegistered ? 1 : 0,
            'vat_number' => $settings->vatNumber,
            'created_at' => $settings->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $settings->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): CompanySettings
    {
        return new CompanySettings(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            isVatRegistered: (bool) (int) $row['is_vat_registered'],
            vatNumber: isset($row['vat_number']) && $row['vat_number'] !== null ? (string) $row['vat_number'] : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
        );
    }
}
