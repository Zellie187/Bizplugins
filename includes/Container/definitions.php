<?php

declare(strict_types=1);

use BizHub\Workflow\Contracts\WorkflowEngineInterface;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\Repositories\WorkflowRepository;
use BizHub\Workflow\Services\WorkflowManager;

/*
 * Contributed into BizHub's shared container via the
 * 'bizhub/container_definitions' filter - see bizupkeep-workflow.php.
 * Concrete classes (WorkflowStateMachine, guards, definitions,
 * controllers, etc.) do not need entries here: PHP-DI autowires them
 * automatically. Only interface -> concrete bindings need to be
 * declared explicitly.
 *
 * WorkflowManager is bound under its own concrete class name, with
 * WorkflowEngineInterface aliased to it via DI\get(), rather than
 * binding the interface directly to DI\autowire(WorkflowManager::class).
 * This matters here specifically because WorkflowManager is stateful:
 * concrete workflow Service Providers (e.g. CompanyRegistrationServiceProvider)
 * type-hint the concrete WorkflowManager class to call registerDefinition()
 * during boot(), while everything else resolves it via
 * WorkflowEngineInterface. Both lookups must return the exact same
 * instance, or registered definitions would be invisible to the
 * instance actually used to create/transition workflows.
 */
return [

    WorkflowRepositoryInterface::class => DI\autowire(WorkflowRepository::class),

    WorkflowManager::class => DI\autowire(),

    WorkflowEngineInterface::class => DI\get(WorkflowManager::class),

];
