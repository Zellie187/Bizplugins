# BizUpKeep Platform Architecture

BizUpKeep is a South African company registration and compliance platform built as a set of cooperating WordPress plugins rather than a single monolith. Three plugins make up the platform today:

- **BizHub** (`BizHub\`) — the shared framework plugin. It owns the PHP-DI dependency injection container, a database abstraction (`BizHub\Framework\Database\Contracts\DatabaseInterface`), an event dispatcher (`BizHub\Framework\Events\EventDispatcher`), structured logging (`BizHub\Framework\Logging\Logger`), and an authorization service (`BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface`). It also owns first-party modules such as Companies, Documents, Notifications, and Reporting.
- **BizUpKeep Core** (`BizUpKeep\Core`) — the site's primary/"main" plugin, providing the platform's own business logic and orchestration on top of BizHub.
- **BizUpKeep Workflow** (`BizHub\Workflow`, this repository) — a business process automation module that adds a generic workflow engine plus concrete workflow types (starting with Company Registration) to the platform.

## Why a separate plugin

BizUpKeep Workflow is packaged as its own plugin, with its own composer package and its own PSR-4 root (`BizHub\Workflow\`), so that workflow/process concerns can evolve independently of BizHub's core framework and of BizUpKeep Core's business logic. It is not, however, an independent application: its plugin header declares `Requires Plugins: bizhub, bizupkeep-core`, and a runtime `BizHub\Workflow\Bootstrap\DependencyGuard` class enforces the same requirement at boot time, deactivating the plugin with an admin notice if either dependency is missing.

## Single shared container

The most important architectural rule governing this plugin is that it **never builds a second dependency injection container, database connection, or event bus**. Every service it needs — database access, event dispatch, logging, authorization — is obtained from BizHub's single, shared PHP-DI container. BizUpKeep Workflow contributes its own bindings and Service Providers into that same container via two extension points BizHub exposes for this purpose (see `Integration-Architecture.md` for the mechanics): the `bizhub/container_definitions` filter and the `bizhub/register_providers` action.

## Layering

Every concrete workflow follows the same layering, mirroring BizHub's own module conventions: **Controller → Service → Workflow Engine → Repository → Framework Database**. A REST controller never touches the workflow engine or the database directly; a workflow-specific Service class (e.g. `CompanyRegistrationService`) is the only caller of the generic `WorkflowEngineInterface`; the engine (`WorkflowManager`) is the only caller of `WorkflowRepositoryInterface`; and the repository is the only code that talks to BizHub's `DatabaseInterface`.

See `Module-Architecture.md` and `Workflow-Architecture.md` for how this plugin is organized internally, and `Integration-Architecture.md` for exactly how it plugs into BizHub.
