<?php

declare(strict_types=1);

namespace BizHub\Workflow\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Workflow\Services\WorkflowManager;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationGuard;

/**
 * Registers the Company Registration workflow type with the workflow
 * engine.
 *
 * This provider is the wiring point new workflow types plug into: to
 * add another concrete workflow (e.g. Director Changes), add its own
 * definition/guard classes and a Service Provider following this same
 * pattern, then list it alongside this one in bizupkeep-workflow.php's
 * 'bizhub/register_providers' callback.
 *
 * @package BizHub\Workflow\Providers
 */
final class CompanyRegistrationServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly WorkflowManager $workflowManager,
        private readonly CompanyRegistrationDefinition $definition,
        private readonly CompanyRegistrationGuard $guard,
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
