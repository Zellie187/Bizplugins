<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Tests\Unit\Services;

use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use BizUpKeep\Core\Services\ServiceRepository;
use BizUpKeep\Core\Tests\Mocks\InMemoryDatabase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ServiceRepositoryTest extends TestCase
{
    private InMemoryDatabase $database;

    private ServiceRepository $repository;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase();
        $this->repository = new ServiceRepository($this->database);
    }

    public function testSaveThenFindByKeyRoundTrips(): void
    {
        $service = $this->makeService();

        $this->repository->save($service);

        $found = $this->repository->findByKey('registration');

        self::assertNotNull($found);
        self::assertSame($service->uuid, $found->uuid);
        self::assertSame($service->name, $found->name);
        self::assertSame(ServicePricingMode::Fixed, $found->pricingMode);
        self::assertSame('new-company-registration', $found->productSlug);
        self::assertNull($found->productSku);
        self::assertSame(ServiceVatTreatment::None, $found->vatTreatment);
        self::assertFalse($found->isRecurring);
        self::assertTrue($found->isActive);
    }

    public function testFindByKeyReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repository->findByKey('does-not-exist'));
    }

    public function testSaveUpdatesExistingRowByUuid(): void
    {
        $service = $this->makeService();
        $this->repository->save($service);

        $updated = new Service(
            uuid: $service->uuid,
            serviceKey: $service->serviceKey,
            name: $service->name,
            pricingMode: $service->pricingMode,
            productSku: $service->productSku,
            productSlug: $service->productSlug,
            vatTreatment: ServiceVatTreatment::Exclusive,
            isRecurring: true,
            notes: 'Updated by staff',
            isActive: false,
            createdAt: $service->createdAt,
            updatedAt: new DateTimeImmutable('2026-02-01 00:00:00'),
        );

        $this->repository->save($updated);

        $found = $this->repository->findByKey('registration');

        self::assertNotNull($found);
        self::assertSame(ServiceVatTreatment::Exclusive, $found->vatTreatment);
        self::assertTrue($found->isRecurring);
        self::assertSame('Updated by staff', $found->notes);
        self::assertFalse($found->isActive);
        self::assertCount(1, $this->database->all('bizhub_core_services'));
    }

    public function testFindAllOnlyActiveFiltersOutInactiveRows(): void
    {
        $this->repository->save($this->makeService(serviceKey: 'registration', isActive: true));
        $this->repository->save($this->makeService(
            serviceKey: 'annual_return_fee',
            uuid: '22222222-2222-2222-2222-222222222222',
            productSlug: null,
            productSku: 'bizupkeep-annual-return-fee',
            isActive: false
        ));

        self::assertCount(2, $this->repository->findAll());
        self::assertCount(1, $this->repository->findAll(onlyActive: true));
        self::assertSame('registration', $this->repository->findAll(onlyActive: true)[0]->serviceKey);
    }

    private function makeService(
        string $uuid = '11111111-1111-1111-1111-111111111111',
        string $serviceKey = 'registration',
        ?string $productSku = null,
        ?string $productSlug = 'new-company-registration',
        bool $isActive = true
    ): Service {
        return new Service(
            uuid: $uuid,
            serviceKey: $serviceKey,
            name: 'Company Registration',
            pricingMode: ServicePricingMode::Fixed,
            productSku: $productSku,
            productSlug: $productSlug,
            vatTreatment: ServiceVatTreatment::None,
            isRecurring: false,
            notes: '',
            isActive: $isActive,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
        );
    }
}
