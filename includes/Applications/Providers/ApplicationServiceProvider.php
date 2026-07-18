<?php

declare(strict_types=1);

namespace BizHub\Applications\Providers;

use BizHub\Applications\Contracts\ApplicationServiceInterface;
use BizHub\Applications\Services\ApplicationWorkflowService;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * Applications Service Provider.
 *
 * Exposes the Applications module's services to the rest of the
 * application. Bindings are declared in Applications/definitions.php.
 *
 * @package BizHub\Applications\Providers
 */
final class ApplicationServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly ApplicationServiceInterface $applicationService,
        private readonly ApplicationWorkflowService $workflowService
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        // Bindings are declared in Applications/definitions.php.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
    }

    /**
     * Return the Application service.
     */
    public function applicationService(): ApplicationServiceInterface
    {
        return $this->applicationService;
    }

    /**
     * Return the Application workflow service.
     */
    public function workflowService(): ApplicationWorkflowService
    {
        return $this->workflowService;
    }
}
