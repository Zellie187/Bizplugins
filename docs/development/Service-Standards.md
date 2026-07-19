# Service Standards

## Two kinds of "service" in this codebase

This plugin has two distinct kinds of Service-layer class, and the distinction matters:

1. **The engine's own Service/Facade — `WorkflowManager`.** Generic across every workflow type; implements `WorkflowEngineInterface`; the only class that talks to `WorkflowRepositoryInterface`, `WorkflowStateMachine`, `TransitionGuardInterface`, the `EventDispatcher`, and the `Logger` on behalf of a workflow operation.
2. **A per-workflow-type Service — e.g. `CompanyRegistrationService`.** Sits *above* the engine, owning cross-module business rules the engine itself must never know about (e.g. "the company must actually exist," enforced via `BizHub\Companies\Contracts\CompanyServiceInterface`). It is the only class Controllers for that workflow type are allowed to call — never `WorkflowEngineInterface` directly.

This gives the full layering `Controller → (workflow-type) Service → WorkflowEngineInterface (WorkflowManager) → WorkflowRepositoryInterface → DatabaseInterface` demanded by BH-WORKFLOW-SPEC-001 section 4.

## The `WorkflowManager` / `WorkflowEngineInterface` aliasing pattern

`includes/Container/definitions.php` binds `WorkflowManager` in an unusual, deliberate way:

```php
WorkflowManager::class => DI\autowire(),
WorkflowEngineInterface::class => DI\get(WorkflowManager::class),
```

Rather than binding the interface straight to `DI\autowire(WorkflowManager::class)`. The reason is that `WorkflowManager` is **stateful**: it holds an in-memory registry of every registered `WorkflowDefinitionInterface`/`TransitionGuardInterface`, populated once per request by each workflow type's Service Provider calling `registerDefinition()` during `boot()`. `CompanyRegistrationServiceProvider` type-hints the *concrete* `WorkflowManager` class in its constructor (because `registerDefinition()` is not part of `WorkflowEngineInterface` — it is an internal wiring method, not part of the public engine contract), while every other consumer (Controllers, other Services) resolves it via `WorkflowEngineInterface`. Both lookups **must** resolve to the exact same object instance, or definitions registered against the concrete-class instance would be invisible to the instance actually used to create/transition workflows. `DI\get(WorkflowManager::class)` guarantees this: it does not create a second instance, it aliases the interface to whatever singleton PHP-DI already produced for the concrete class.

## A Service class's job is narrow

A per-workflow-type Service class should do exactly four things, following `CompanyRegistrationService`'s shape: validate that a requested action belongs to that workflow type (`ValidationException` if not), enforce that a workflow instance found by UUID is actually of the expected workflow type (`assertIsCompanyRegistration()`, throwing `WorkflowNotFoundException` on a type mismatch — this prevents cross-workflow-type UUID confusion at the API boundary), delegate the actual state change to `WorkflowEngineInterface`, and own any pre-engine business rule that depends on another module (never duplicated inside the engine itself).

## What a Service class must never do

Never construct a `WorkflowRepository` or touch `DatabaseInterface` directly; never inspect a `WorkflowDefinitionInterface`'s transition rules itself (that authority belongs solely to `WorkflowStateMachine`, reached only through the engine); never catch and swallow an engine exception — let it propagate to the Controller, which is the only layer responsible for translating it into an HTTP response.
