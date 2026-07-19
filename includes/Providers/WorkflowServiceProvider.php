<?php

declare(strict_types=1);

namespace BizHub\Workflow\Providers;

use BizHub\Framework\Events\EventDispatcher;
use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizHub\Workflow\Events\WorkflowTransitioned;
use BizHub\Workflow\Notifications\WorkflowNotificationListener;
use BizHub\Workflow\Policies\Capabilities;

/**
 * Registers the generic workflow engine into BizHub's shared
 * container/provider lifecycle.
 *
 * Bindings for the engine's own services (WorkflowEngineInterface,
 * WorkflowRepositoryInterface) are declared in Container/definitions.php,
 * contributed via the 'bizhub/container_definitions' filter. This
 * provider's job is the runtime registration work a definitions file
 * cannot do: telling BizHub's AuthorizationService which capabilities
 * this plugin introduces, and wiring the notification listener onto
 * BizHub's shared event dispatcher.
 *
 * @package BizHub\Workflow\Providers
 */
final class WorkflowServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly EventDispatcher $events,
        private readonly WorkflowNotificationListener $notificationListener,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        // Bindings are declared in includes/Container/definitions.php.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        foreach (Capabilities::all() as $capability) {
            $this->authorizationService->registerCapability($capability);
        }

        $this->events->listen(WorkflowTransitioned::class, $this->notificationListener);
    }
}
