<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Companies\Entities;

use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Entities\Director;
use BizHub\Companies\Entities\RegisteredAddress;
use BizHub\Framework\Support\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
    private function makeAddress(): RegisteredAddress
    {
        return new RegisteredAddress('1 Main Rd', '', 'Suburb', 'Cape Town', 'Western Cape', '8001');
    }

    public function test_creates_valid_company(): void
    {
        $company = new Company(
            Uuid::generate(),
            1,
            '2024/123456/07',
            'Test Co',
            'Private Company',
            CompanyStatus::ACTIVE,
            $this->makeAddress()
        );

        $this->assertSame('Test Co', $company->getCompanyName());
        $this->assertTrue($company->getStatus()->isActive());
    }

    public function test_rejects_invalid_client_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Company(Uuid::generate(), 0, '2024/123456/07', 'Test Co', 'Private Company', CompanyStatus::ACTIVE, $this->makeAddress());
    }

    public function test_add_director_assigns_company_uuid(): void
    {
        $company = new Company(
            Uuid::generate(),
            1,
            '2024/123456/07',
            'Test Co',
            'Private Company',
            CompanyStatus::ACTIVE,
            $this->makeAddress()
        );

        $director = new Director(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable());
        $company->addDirector($director);

        $this->assertSame($company->getUuid(), $director->getCompanyUuid());
        $this->assertCount(1, $company->getDirectors());
    }

    public function test_add_director_is_idempotent(): void
    {
        $company = new Company(
            Uuid::generate(),
            1,
            '2024/123456/07',
            'Test Co',
            'Private Company',
            CompanyStatus::ACTIVE,
            $this->makeAddress()
        );

        $director = new Director(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable());
        $company->addDirector($director);
        $company->addDirector($director);

        $this->assertCount(1, $company->getDirectors());
    }

    public function test_remove_director(): void
    {
        $company = new Company(
            Uuid::generate(),
            1,
            '2024/123456/07',
            'Test Co',
            'Private Company',
            CompanyStatus::ACTIVE,
            $this->makeAddress()
        );

        $director = new Director(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable());
        $company->addDirector($director);
        $company->removeDirector($director->getUuid());

        $this->assertCount(0, $company->getDirectors());
    }
}
