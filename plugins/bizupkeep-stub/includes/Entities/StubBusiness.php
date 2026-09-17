<?php

declare(strict_types=1);

namespace BizHub\Stub\Entities;

use DateTimeImmutable;

/**
 * Links a BizHub Company to the Stub "business" it has been
 * provisioned as, and tracks whether its historical ledger data has
 * been migrated in.
 *
 * @package BizHub\Stub\Entities
 */
final readonly class StubBusiness
{
    public function __construct(
        public string $companyUuid,
        public string $stubBusinessUid,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $migratedAt = null,
        public ?string $migrationFailedReason = null
    ) {
    }

    public function withMigrated(DateTimeImmutable $migratedAt): self
    {
        return new self($this->companyUuid, $this->stubBusinessUid, $this->createdAt, $migratedAt, null);
    }

    public function withMigrationFailed(string $reason): self
    {
        return new self($this->companyUuid, $this->stubBusinessUid, $this->createdAt, null, $reason);
    }

    public function isMigrated(): bool
    {
        return $this->migratedAt !== null;
    }
}
