<?php

declare(strict_types=1);

namespace BizHub\Applications\Contracts;

use BizHub\Applications\DTO\ApplicationData;
use BizHub\Applications\DTO\ApplicationSummary;
use BizHub\Applications\Entities\Application;

/**
 * Defines the business operations for Application management.
 *
 * @package BizHub\Applications\Contracts
 */
interface ApplicationServiceInterface
{
    /**
     * Create an application.
     */
    public function createApplication(ApplicationData $applicationData): Application;

    /**
     * Retrieve an application.
     */
    public function getApplication(string $uuid): Application;

    /**
     * Delete an application.
     */
    public function deleteApplication(string $uuid): void;

    /**
     * Retrieve all applications for a client.
     *
     * @return Application[]
     */
    public function getApplicationsForClient(int $clientId): array;

    /**
     * Retrieve application summaries for a client.
     *
     * @return ApplicationSummary[]
     */
    public function getApplicationSummaries(int $clientId): array;
}
