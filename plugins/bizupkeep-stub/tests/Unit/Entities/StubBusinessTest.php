<?php

declare(strict_types=1);

namespace BizHub\Stub\Tests\Unit\Entities;

use BizHub\Stub\Entities\StubBusiness;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StubBusinessTest extends TestCase
{
    public function test_new_business_is_not_migrated(): void
    {
        $business = new StubBusiness(
            companyUuid: 'company-uuid',
            stubBusinessUid: 'stub-uid',
            createdAt: new DateTimeImmutable()
        );

        self::assertFalse($business->isMigrated());
        self::assertNull($business->migratedAt);
        self::assertNull($business->migrationFailedReason);
    }

    public function test_with_migrated_marks_it_migrated_and_clears_any_failure_reason(): void
    {
        $failed = (new StubBusiness('company-uuid', 'stub-uid', new DateTimeImmutable()))
            ->withMigrationFailed('boom');

        $migratedAt = new DateTimeImmutable();
        $migrated = $failed->withMigrated($migratedAt);

        self::assertTrue($migrated->isMigrated());
        self::assertSame($migratedAt, $migrated->migratedAt);
        self::assertNull($migrated->migrationFailedReason);
    }

    public function test_with_migration_failed_records_the_reason_and_stays_unmigrated(): void
    {
        $business = new StubBusiness('company-uuid', 'stub-uid', new DateTimeImmutable());
        $failed = $business->withMigrationFailed('Stub API returned HTTP 500.');

        self::assertFalse($failed->isMigrated());
        self::assertSame('Stub API returned HTTP 500.', $failed->migrationFailedReason);
    }
}
