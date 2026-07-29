<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\BankImport;

use BizHub\Bookkeeping\Contracts\ImportMappingRepositoryInterface;
use BizHub\Bookkeeping\Entities\ImportMapping;
use BizHub\Bookkeeping\Enums\ImportAmountStyle;
use BizHub\Framework\Database\Contracts\DatabaseInterface;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the import mappings
 * table.
 *
 * @package BizHub\Bookkeeping\BankImport
 */
final class ImportMappingRepository implements ImportMappingRepositoryInterface
{
    private const TABLE = 'bizhub_bookkeeping_import_mappings';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid): ?ImportMapping
    {
        $row = $this->database->findOne(self::TABLE, ['company_uuid' => $companyUuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function save(ImportMapping $mapping): ImportMapping
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $mapping->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($mapping), ['uuid' => $mapping->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($mapping));
        }

        return $mapping;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(ImportMapping $mapping): array
    {
        return [
            'uuid' => $mapping->uuid,
            'company_uuid' => $mapping->companyUuid,
            'date_column' => $mapping->dateColumn,
            'description_column' => $mapping->descriptionColumn,
            'amount_style' => $mapping->amountStyle->value,
            'amount_column' => $mapping->amountColumn,
            'debit_column' => $mapping->debitColumn,
            'credit_column' => $mapping->creditColumn,
            'date_format' => $mapping->dateFormat,
            'created_at' => $mapping->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $mapping->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): ImportMapping
    {
        return new ImportMapping(
            uuid: (string) $row['uuid'],
            companyUuid: (string) $row['company_uuid'],
            dateColumn: (string) $row['date_column'],
            descriptionColumn: (string) $row['description_column'],
            amountStyle: ImportAmountStyle::from((string) $row['amount_style']),
            amountColumn: $this->nullableString($row['amount_column'] ?? null),
            debitColumn: $this->nullableString($row['debit_column'] ?? null),
            creditColumn: $this->nullableString($row['credit_column'] ?? null),
            dateFormat: (string) $row['date_format'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
