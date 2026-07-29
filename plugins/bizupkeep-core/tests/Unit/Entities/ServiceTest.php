<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Tests\Unit\Entities;

use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public function testRejectsEmptyServiceKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeService(serviceKey: '');
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeService(name: '');
    }

    public function testRejectsBothProductSkuAndProductSlugSet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeService(productSku: 'some-sku', productSlug: 'some-slug');
    }

    public function testRejectsNeitherProductSkuNorProductSlugSet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeService(productSku: null, productSlug: null);
    }

    public function testAcceptsProductSkuOnly(): void
    {
        $service = $this->makeService(productSku: 'some-sku', productSlug: null);

        self::assertSame('some-sku', $service->productSku);
        self::assertNull($service->productSlug);
    }

    public function testAcceptsProductSlugOnly(): void
    {
        $service = $this->makeService(productSku: null, productSlug: 'some-slug');

        self::assertSame('some-slug', $service->productSlug);
        self::assertNull($service->productSku);
    }

    private function makeService(
        string $serviceKey = 'registration',
        string $name = 'Company Registration',
        ?string $productSku = null,
        ?string $productSlug = 'new-company-registration'
    ): Service {
        return new Service(
            uuid: '11111111-1111-1111-1111-111111111111',
            serviceKey: $serviceKey,
            name: $name,
            pricingMode: ServicePricingMode::Fixed,
            productSku: $productSku,
            productSlug: $productSlug,
            vatTreatment: ServiceVatTreatment::None,
            isRecurring: false,
            notes: '',
            isActive: true,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
        );
    }
}
