<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Services;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use DateTimeImmutable;

/**
 * The only class touching DatabaseInterface for the Service catalog
 * table.
 *
 * @package BizUpKeep\Core\Services
 */
final class ServiceRepository implements ServiceRepositoryInterface
{
    private const TABLE = 'bizhub_core_services';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    public function findByKey(string $serviceKey): ?Service
    {
        $row = $this->database->findOne(self::TABLE, ['service_key' => $serviceKey]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return Service[]
     */
    public function findAll(bool $onlyActive = false): array
    {
        $criteria = $onlyActive ? ['is_active' => 1] : [];

        $rows = $this->database->findAll(self::TABLE, $criteria, ['service_key' => 'ASC']);

        return array_map($this->hydrate(...), $rows);
    }

    public function save(Service $service): Service
    {
        if ($this->database->exists(self::TABLE, ['uuid' => $service->uuid])) {
            $this->database->update(self::TABLE, $this->dehydrate($service), ['uuid' => $service->uuid]);
        } else {
            $this->database->insert(self::TABLE, $this->dehydrate($service));
        }

        return $service;
    }

    /**
     * @return array<string,mixed>
     */
    private function dehydrate(Service $service): array
    {
        return [
            'uuid' => $service->uuid,
            'service_key' => $service->serviceKey,
            'name' => $service->name,
            'pricing_mode' => $service->pricingMode->value,
            'product_sku' => $service->productSku,
            'product_slug' => $service->productSlug,
            'vat_treatment' => $service->vatTreatment->value,
            'is_recurring' => $service->isRecurring ? 1 : 0,
            'notes' => $service->notes,
            'is_active' => $service->isActive ? 1 : 0,
            'created_at' => $service->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $service->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Service
    {
        return new Service(
            uuid: (string) $row['uuid'],
            serviceKey: (string) $row['service_key'],
            name: (string) $row['name'],
            pricingMode: ServicePricingMode::from((string) $row['pricing_mode']),
            productSku: isset($row['product_sku']) && $row['product_sku'] !== null
                ? (string) $row['product_sku']
                : null,
            productSlug: isset($row['product_slug']) && $row['product_slug'] !== null
                ? (string) $row['product_slug']
                : null,
            vatTreatment: ServiceVatTreatment::from((string) $row['vat_treatment']),
            isRecurring: (bool) (int) $row['is_recurring'],
            notes: (string) $row['notes'],
            isActive: (bool) (int) $row['is_active'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== null
                ? new DateTimeImmutable((string) $row['updated_at'])
                : null,
        );
    }
}
