<?php

declare(strict_types=1);

namespace BizHub\Applications\Contracts;

use BizHub\Applications\DTO\ApplicationSummary;
use BizHub\Applications\Entities\Application;

/**
 * Defines the persistence contract for Application entities.
 *
 * @package BizHub\Applications\Contracts
 */
interface ApplicationRepositoryInterface
{
    /**
     * Find an application by its internal database identifier.
     */
    public function find(int $id): ?Application;

    /**
     * Find an application by UUID.
     */
    public function findByUuid(string $uuid): ?Application;

    /**
     * Retrieve all applications belonging to a client.
     *
     * @return Application[]
     */
    public function findByClientId(int $clientId): array;

    /**
     * Retrieve lightweight application summaries for a client.
     *
     * @return ApplicationSummary[]
     */
    public function findSummariesByClientId(int $clientId): array;

    /**
     * Persist an application.
     */
    public function save(Application $application): Application;

    /**
     * Delete an application.
     */
    public function delete(Application $application): void;
}
