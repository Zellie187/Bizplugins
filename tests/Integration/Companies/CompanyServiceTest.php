<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\Companies;

use BizHub\Companies\DTO\AddressData;
use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\DTO\DirectorData;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Companies\Exceptions\InvalidCompanyException;
use BizHub\Companies\Repositories\CompanyRepository;
use BizHub\Companies\Repositories\DirectorRepository;
use BizHub\Companies\Services\CompanyLookupService;
use BizHub\Companies\Services\CompanyService;
use BizHub\Companies\Services\DirectorService;
use BizHub\Framework\Support\Uuid;
use BizHub\Tests\Mocks\InMemoryDatabase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CompanyServiceTest extends TestCase
{
    private CompanyService $companyService;
    private DirectorService $directorService;
    private CompanyLookupService $lookupService;
    private CompanyRepository $companyRepository;
    private DirectorRepository $directorRepository;

    protected function setUp(): void
    {
        $db = new InMemoryDatabase();
        $this->directorRepository = new DirectorRepository($db);
        $this->companyRepository = new CompanyRepository($db, $this->directorRepository);
        $this->companyService = new CompanyService($this->companyRepository);
        $this->directorService = new DirectorService($this->directorRepository, $this->companyRepository);
        $this->lookupService = new CompanyLookupService($this->companyRepository);
    }

    private function makeCompanyData(string $uuid, string $registrationNumber): CompanyData
    {
        return new CompanyData(
            $uuid,
            42,
            $registrationNumber,
            'Acme Trading (Pty) Ltd',
            'Private Company',
            CompanyStatus::ACTIVE,
            new AddressData('1 Main Rd', '', 'Sea Point', 'Cape Town', 'Western Cape', '8005')
        );
    }

    public function test_create_and_fetch_company(): void
    {
        $uuid = Uuid::generate();
        $this->companyService->createCompany($this->makeCompanyData($uuid, '2024/000123/07'));

        $fetched = $this->companyService->getCompany($uuid);

        $this->assertSame('Acme Trading (Pty) Ltd', $fetched->getCompanyName());
    }

    public function test_duplicate_registration_number_rejected(): void
    {
        $this->companyService->createCompany($this->makeCompanyData(Uuid::generate(), '2024/000123/07'));

        $this->expectException(InvalidCompanyException::class);

        $this->companyService->createCompany($this->makeCompanyData(Uuid::generate(), '2024/000123/07'));
    }

    public function test_get_missing_company_throws(): void
    {
        $this->expectException(CompanyNotFoundException::class);

        $this->companyService->getCompany(Uuid::generate());
    }

    public function test_director_lifecycle_and_company_hydration(): void
    {
        $uuid = Uuid::generate();
        $this->companyService->createCompany($this->makeCompanyData($uuid, '2024/000999/07'));

        $director = $this->directorService->addDirectorToCompany(
            $uuid,
            new DirectorData(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable('2020-01-01'))
        );

        $withDirector = $this->companyService->getCompany($uuid);
        $this->assertCount(1, $withDirector->getDirectors());

        $this->directorService->resignDirector($director->getUuid(), new DateTimeImmutable('2026-01-01'));
        $this->assertFalse($this->directorService->getDirector($director->getUuid())->isActive());
    }

    public function test_summaries_include_director_count(): void
    {
        $uuid = Uuid::generate();
        $this->companyService->createCompany($this->makeCompanyData($uuid, '2024/000555/07'));
        $this->directorService->addDirectorToCompany(
            $uuid,
            new DirectorData(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable('2020-01-01'))
        );

        $summaries = $this->companyService->getCompanySummaries(42);

        $this->assertCount(1, $summaries);
        $this->assertSame(1, $summaries[0]->directorCount);
    }

    public function test_lookup_search_matches_name_or_registration_number(): void
    {
        $this->companyService->createCompany($this->makeCompanyData(Uuid::generate(), '2024/000777/07'));

        $this->assertCount(1, $this->lookupService->search(42, 'acme'));
        $this->assertCount(0, $this->lookupService->search(42, 'nonexistent'));
    }

    public function test_delete_cascades_directors(): void
    {
        $uuid = Uuid::generate();
        $this->companyService->createCompany($this->makeCompanyData($uuid, '2024/000888/07'));
        $director = $this->directorService->addDirectorToCompany(
            $uuid,
            new DirectorData(Uuid::generate(), 'Jane', 'Doe', '8001015800086', null, new DateTimeImmutable('2020-01-01'))
        );

        $this->companyService->deleteCompany($uuid);

        $this->assertNull($this->companyRepository->findByUuid($uuid));
        $this->assertNull($this->directorRepository->findByUuid($director->getUuid()));
    }
}
