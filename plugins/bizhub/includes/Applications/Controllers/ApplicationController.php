<?php

declare(strict_types=1);

namespace BizHub\Applications\Controllers;

use BizHub\Applications\Contracts\ApplicationServiceInterface;
use BizHub\Applications\DTO\ApplicationData;
use BizHub\Applications\Services\ApplicationWorkflowService;

/**
 * Handles application view and workflow operations.
 *
 * Transport-agnostic: route registration belongs to the API/Admin layers.
 *
 * @package BizHub\Applications\Controllers
 */
final class ApplicationController
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications,
        private readonly ApplicationWorkflowService $workflow
    ) {
    }

    /**
     * Create a new application.
     *
     * @return array<string,mixed>
     */
    public function create(ApplicationData $applicationData): array
    {
        return $this->applications->createApplication($applicationData)->toArray();
    }

    /**
     * Retrieve a single application.
     *
     * @return array<string,mixed>
     */
    public function show(string $uuid): array
    {
        return $this->applications->getApplication($uuid)->toArray();
    }

    /**
     * List application summaries for a client.
     *
     * @return array<int,array<string,mixed>>
     */
    public function indexForClient(int $clientId): array
    {
        return array_map(
            static fn ($summary): array => $summary->toArray(),
            $this->applications->getApplicationSummaries($clientId)
        );
    }

    /**
     * Submit an application for review.
     *
     * @return array<string,mixed>
     */
    public function submit(string $uuid): array
    {
        return $this->workflow->submit($uuid)->toArray();
    }
}
