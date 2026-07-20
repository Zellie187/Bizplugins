<?php

declare(strict_types=1);

namespace BizHub\Workflow\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Workflow\Services\WorkflowManager;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentGuard;

/**
 * Registers the Company Amendment workflow type with the workflow
 * engine, following the same registration pattern as
 * CompanyRegistrationServiceProvider.
 *
 * @package BizHub\Workflow\Providers
 */
final class CompanyAmendmentServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly WorkflowManager $workflowManager,
        private readonly CompanyAmendmentDefinition $definition,
        private readonly CompanyAmendmentGuard $guard,
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
