<?php

declare(strict_types=1);

namespace BizHub\Applications\Services;

use BizHub\Applications\Contracts\ApplicationRepositoryInterface;
use BizHub\Applications\Contracts\ApplicationServiceInterface;
use BizHub\Applications\DTO\ApplicationData;
use BizHub\Applications\Entities\Application;
use BizHub\Applications\Exceptions\ApplicationNotFoundException;

/**
 * Implements the business operations for Application management.
 *
 * @package BizHub\Applications\Services
 */
final class ApplicationService implements ApplicationServiceInterface
{
    public function __construct(
        private readonly ApplicationRepositoryInterface $applications
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function createApplication(ApplicationData $applicationData): Application
    {
        $application = new Application(
            $applicationData->uuid,
            $applicationData->clientId,
            $applicationData->type,
            $applicationData->status,
            $applicationData->companyUuid
        );

        return $this->applications->save($application);
    }

    /**
     * {@inheritDoc}
     */
    public function getApplication(string $uuid): Application
    {
        return $this->applications->findByUuid($uuid)
            ?? throw ApplicationNotFoundException::withUuid($uuid);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteApplication(string $uuid): void
    {
        $this->applications->delete($this->getApplication($uuid));
    }

    /**
     * {@inheritDoc}
     */
    public function getApplicationsForClient(int $clientId): array
    {
        return $this->applications->findByClientId($clientId);
    }

    /**
     * {@inheritDoc}
     */
    public function getApplicationSummaries(int $clientId): array
    {
        return $this->applications->findSummariesByClientId($clientId);
    }
}
