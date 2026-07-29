<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Unit\Accounts;

use BizHub\Bookkeeping\Accounts\CompanySettingsRepository;
use BizHub\Bookkeeping\Entities\CompanySettings;
use BizHub\Bookkeeping\Tests\Mocks\InMemoryDatabase;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CompanySettingsRepositoryTest extends TestCase
{
    private const COMPANY = 'company-1';

    private CompanySettingsRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new CompanySettingsRepository(new InMemoryDatabase());
    }

    public function testFindByCompanyUuidReturnsNullWhenNoRowExists(): void
    {
        self::assertNull($this->repository->findByCompanyUuid(self::COMPANY));
    }

    public function testSaveThenFindRoundTripsVatRegistration(): void
    {
        $this->repository->save(new CompanySettings(
            uuid: Uuid::generate(),
            companyUuid: self::COMPANY,
            isVatRegistered: true,
            vatNumber: '4123456789',
            createdAt: new DateTimeImmutable(),
        ));

        $found = $this->repository->findByCompanyUuid(self::COMPANY);

        self::assertNotNull($found);
        self::assertTrue($found->isVatRegistered);
        self::assertSame('4123456789', $found->vatNumber);
    }

    public function testSavingAgainWithTheSameUuidUpdatesTheExistingRow(): void
    {
        $uuid = Uuid::generate();

        $this->repository->save(new CompanySettings(
            uuid: $uuid,
            companyUuid: self::COMPANY,
            isVatRegistered: false,
            vatNumber: null,
            createdAt: new DateTimeImmutable(),
        ));

        $this->repository->save(new CompanySettings(
            uuid: $uuid,
            companyUuid: self::COMPANY,
            isVatRegistered: true,
            vatNumber: '4999999999',
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        ));

        $found = $this->repository->findByCompanyUuid(self::COMPANY);

        self::assertNotNull($found);
        self::assertTrue($found->isVatRegistered);
        self::assertSame('4999999999', $found->vatNumber);
    }
}
