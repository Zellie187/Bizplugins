<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Tests\Mocks;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use RuntimeException;

/**
 * Minimal CompanyServiceInterface double for tests, backed by a fixed
 * companyUuid => Company map handed in by the test. Only getCompany()
 * is meaningfully implemented - SubscriptionReminderService is the
 * only caller under test, and it never calls anything else on this
 * interface.
 */
final class FakeCompanyService implements CompanyServiceInterface
{
    /**
     * @param array<string,Company> $companiesByUuid
     */
    public function __construct(
        private readonly array $companiesByUuid
    ) {
    }

    public function createCompany(CompanyData $companyData): Company
    {
        throw new RuntimeException('Not implemented in this test double.');
    }

    public function updateCompany(CompanyData $companyData): Company
    {
        throw new RuntimeException('Not implemented in this test double.');
    }

    public function getCompany(string $uuid): Company
    {
        return $this->companiesByUuid[$uuid] ?? throw CompanyNotFoundException::withUuid($uuid);
    }

    public function deleteCompany(string $uuid): void
    {
        throw new RuntimeException('Not implemented in this test double.');
    }

    public function getCompaniesForClient(int $clientId): array
    {
        return [];
    }

    public function getCompanySummaries(int $clientId): array
    {
        return [];
    }
}
