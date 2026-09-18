<?php

declare(strict_types=1);

namespace BizHub\Stub\Repositories;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Stub\Contracts\StubBusinessRepositoryInterface;
use BizHub\Stub\Entities\StubBusiness;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the
 * bizhub_stub_businesses table.
 *
 * @package BizHub\Stub\Repositories
 */
final class StubBusinessRepository implements StubBusinessRepositoryInterface
{
    private const TABLE = 'bizhub_stub_businesses';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByCompanyUuid(string $companyUuid): ?StubBusiness
    {
        $row = $this->database->findOne(self::TABLE, ['company_uuid' => $companyUuid]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function save(StubBusiness $business): StubBusiness
    {
        if ($this->database->exists(self::TABLE, ['company_uuid' => $business->companyUuid])) {
            $this->database->update(
                self::TABLE,
                $this->dehydrate($business),
                ['company_uuid' => $business->companyUuid]
            );
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($business));
        }

        return $business;
    }

    /**
     * @return StubBusiness[]
     */
    public function findAll(): array
    {
        $rows = $this->database->findAll(self::TABLE, [], ['created_at' => 'DESC']);

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(StubBusiness $business): array
    {
        return [
            'company_uuid' => $business->companyUuid,
            'stub_business_uid' => $business->stubBusinessUid,
            'created_at' => $business->createdAt->format('Y-m-d H:i:s'),
            'migrated_at' => $business->migratedAt?->format('Y-m-d H:i:s'),
            'migration_failed_reason' => $business->migrationFailedReason,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): StubBusiness
    {
        return new StubBusiness(
            companyUuid: (string) $row['company_uuid'],
            stubBusinessUid: (string) $row['stub_business_uid'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            migratedAt: isset($row['migrated_at']) && $row['migrated_at'] !== null
                ? new DateTimeImmutable((string) $row['migrated_at'])
                : null,
            migrationFailedReason: isset($row['migration_failed_reason']) && $row['migration_failed_reason'] !== null
                ? (string) $row['migration_failed_reason']
                : null,
        );
    }
}
