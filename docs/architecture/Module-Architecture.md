# Module Architecture

BizUpKeep Workflow is organized as PSR-4 code under `includes/`, autoloaded as `BizHub\Workflow\` (see `composer.json`). The directory layout separates the generic workflow engine from concrete workflow types built on top of it.

```
includes/
├── Admin/            WorkflowAdminMenu (admin screen)
├── Bootstrap/        Constants, DependencyGuard, Plugin
├── Container/        definitions.php (DI bindings contributed to BizHub)
├── Contracts/        WorkflowEngineInterface, WorkflowDefinitionInterface,
│                     TransitionGuardInterface, WorkflowRepositoryInterface,
│                     CommandInterface
├── DTO/              TransitionRule, Transition, WorkflowSummary,
│                     CreateWorkflowCommand, TransitionWorkflowCommand,
│                     RollbackWorkflowCommand
├── Entities/         WorkflowInstance (aggregate root)
├── Enums/            WorkflowStatus
├── Events/           WorkflowCreated, WorkflowTransitioned, WorkflowCompleted,
│                     WorkflowCancelled, WorkflowRolledBack
├── Exceptions/       WorkflowException hierarchy
├── Http/             Controllers/, Requests/ (REST layer)
├── Install/          Schema, Migrator, Activator, Deactivator, RoleGrant
├── Notifications/    WorkflowNotificationListener
├── Policies/          Capabilities
├── Providers/        WorkflowServiceProvider, CompanyRegistrationServiceProvider
├── Queues/           reserved for future use (currently empty, see Queue-System.md)
├── Repositories/      WorkflowRepository
├── Services/          WorkflowManager (the engine)
├── States/            WorkflowStateMachine
└── Workflows/
    └── CompanyRegistration/
        ├── CompanyRegistrationDefinition.php
        ├── CompanyRegistrationGuard.php
        └── CompanyRegistrationService.php
```

## Two-tier design: engine vs. workflow types

The codebase draws a hard line between the **generic workflow engine** (`Contracts/`, `DTO/`, `Entities/`, `Enums/`, `Events/`, `Exceptions/`, `Repositories/`, `Services/WorkflowManager`, `States/`) and **concrete workflow types** (`Workflows/CompanyRegistration/`). The engine knows nothing about "companies" or "documents" — it only knows statuses, actions, transitions and guards. A concrete workflow type supplies:

1. A `WorkflowDefinitionInterface` implementation declaring its lifecycle (`CompanyRegistrationDefinition`).
2. An optional `TransitionGuardInterface` implementation enforcing its business-rule preconditions (`CompanyRegistrationGuard`).
3. A `Service` class (`CompanyRegistrationService`) that is the only caller of `WorkflowEngineInterface` for that workflow type, and that owns any cross-module rules (e.g. "the company must exist").
4. An HTTP `Controller` and `Request` classes exposing it over REST.
5. A `ServiceProvider` (`CompanyRegistrationServiceProvider`) that registers the definition/guard with the engine during boot.

Adding a new workflow type (Director Changes, Address Changes, etc.) means adding a new subdirectory under `Workflows/` following this exact same five-piece pattern and listing its Service Provider in `bizupkeep-workflow.php`'s `bizhub/register_providers` callback — the engine itself never needs to change.

## Bootstrap layer

`includes/Bootstrap/` holds the WordPress-facing wiring that is deliberately kept outside the DI container: `Constants` (path constants), `DependencyGuard` (the runtime dependency check), and `Plugin` (registers `init`, `rest_api_init`, and `admin_menu` hooks once BizHub has booted). See `BizUpKeep-Architecture.md` and `Integration-Architecture.md` for why this separation exists.
