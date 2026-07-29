<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Contracts;

use BizUpKeep\Core\Entities\Service;

/**
 * Persistence contract for the Service catalog. The full row set is
 * fixed by ServiceCatalogSeeder at activation time - this interface has
 * no create/delete, only lookup and in-place edits of the
 * staff-editable fields (vatTreatment/isRecurring/notes/isActive).
 *
 * @package BizUpKeep\Core\Contracts
 */
interface ServiceRepositoryInterface
{
    public function findByKey(string $serviceKey): ?Service;

    /**
     * @return Service[]
     */
    public function findAll(bool $onlyActive = false): array;

    public function save(Service $service): Service;
}
