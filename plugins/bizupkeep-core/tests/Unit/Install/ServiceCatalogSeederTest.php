<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Tests\Unit\Install;

use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use BizUpKeep\Core\Install\ServiceCatalogSeeder;
use BizUpKeep\Core\Services\ServiceRepository;
use BizUpKeep\Core\Tests\Mocks\InMemoryDatabase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ServiceCatalogSeederTest extends TestCase
{
    private InMemoryDatabase $database;

    private ServiceRepository $repository;

    private ServiceCatalogSeeder $seeder;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
        $this->repository = new ServiceRepository($this->database);
        $this->seeder = new ServiceCatalogSeeder($this->repository);
    }

    public function testSeedsExactlyTenExpectedServiceKeys(): void
    {
        $this->seeder->seed();

        $keys = array_map(static fn ($service) => $service->serviceKey, $this->repository->findAll());
        sort($keys);

        self::assertSame(
            [
                'amendment_address',
                'amendment_address_director',
                'amendment_address_director_name',
                'amendment_address_name',
                'amendment_director',
                'amendment_director_name',
                'amendment_name',
                'annual_return_fee',
                'bookkeeping_monthly',
                'registration',
            ],
            $keys
        );
    }

    public function testBookkeepingMonthlyIsTheOnlyInclusiveAndRecurringRow(): void
    {
        $this->seeder->seed();

        $bookkeeping = $this->repository->findByKey('bookkeeping_monthly');

        self::assertNotNull($bookkeeping);
        self::assertSame(ServiceVatTreatment::Inclusive, $bookkeeping->vatTreatment);
        self::assertTrue($bookkeeping->isRecurring);

        foreach ($this->repository->findAll() as $service) {
            if ($service->serviceKey === 'bookkeeping_monthly') {
                continue;
            }

            self::assertSame(ServiceVatTreatment::None, $service->vatTreatment);
            self::assertFalse($service->isRecurring);
        }
    }

    public function testSeedingTwiceDoesNotDuplicateRows(): void
    {
        $this->seeder->seed();
        $this->seeder->seed();

        self::assertCount(10, $this->repository->findAll());
    }

    public function testSeedingAgainDoesNotClobberAnAlreadyEditedRow(): void
    {
        $this->seeder->seed();

        $edited = $this->repository->findByKey('registration');
        self::assertNotNull($edited);

        $this->repository->save(new Service(
            uuid: $edited->uuid,
            serviceKey: $edited->serviceKey,
            name: $edited->name,
            pricingMode: $edited->pricingMode,
            productSku: $edited->productSku,
            productSlug: $edited->productSlug,
            vatTreatment: ServiceVatTreatment::Exclusive,
            isRecurring: $edited->isRecurring,
            notes: 'Staff decided: VAT exclusive.',
            isActive: $edited->isActive,
            createdAt: $edited->createdAt,
            updatedAt: new DateTimeImmutable(),
        ));

        $this->seeder->seed();

        $stillEdited = $this->repository->findByKey('registration');
        self::assertNotNull($stillEdited);
        self::assertSame(ServiceVatTreatment::Exclusive, $stillEdited->vatTreatment);
        self::assertSame('Staff decided: VAT exclusive.', $stillEdited->notes);
        self::assertCount(10, $this->repository->findAll());
    }
}
