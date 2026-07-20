<?php

declare(strict_types=1);

namespace BizHub\Workflow\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Workflow\Services\WorkflowManager;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnGuard;

/**
 * Registers the Annual Return workflow type with the workflow engine,
 * following the same registration pattern as
 * CompanyRegistrationServiceProvider.
 *
 * @package BizHub\Workflow\Providers
 */
final class AnnualReturnServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly WorkflowManager $workflowManager,
        private readonly AnnualReturnDefinition $definition,
        private readonly AnnualReturnGuard $guard,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        $this->workflowManager->registerDefinition($this->definition, $this->guard);
    }
}
