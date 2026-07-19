# Naming Conventions

## Namespaces and directories

Root namespace `BizHub\Workflow\`, mapped 1:1 onto `includes/` sub-directories matching PSR-4: `BizHub\Workflow\Services\WorkflowManager` lives at `includes/Services/WorkflowManager.php`, `BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationGuard` lives at `includes/Workflows/CompanyRegistration/CompanyRegistrationGuard.php`, and so on throughout the tree. Tests mirror the same structure one level down, under `BizHub\Workflow\Tests\` → `tests/`.

## Class name suffixes signal role

| Suffix | Role | Examples |
|---|---|---|
| `Interface` | Contract | `WorkflowEngineInterface`, `TransitionGuardInterface` |
| `Command` | Command-pattern request DTO | `CreateWorkflowCommand`, `TransitionWorkflowCommand`, `RollbackWorkflowCommand` |
| `Manager` | Stateful orchestrator/facade | `WorkflowManager` |
| `Repository` | Persistence boundary | `WorkflowRepository` |
| `StateMachine` | Structural transition authority | `WorkflowStateMachine` |
| `Definition` | Per-workflow-type lifecycle declaration | `CompanyRegistrationDefinition` |
| `Guard` | Per-workflow-type precondition strategy | `CompanyRegistrationGuard` |
| `Service` | Per-workflow-type orchestration facing controllers | `CompanyRegistrationService` |
| `Controller` | REST HTTP boundary | `CompanyRegistrationController` |
| `Request` | Validated REST input DTO | `StartCompanyRegistrationRequest`, `CompanyRegistrationActionRequest` |
| `Exception` | Error type | `WorkflowNotFoundException`, `PreconditionFailedException` |
| `ServiceProvider` | DI/boot wiring unit | `WorkflowServiceProvider`, `CompanyRegistrationServiceProvider` |
| `Listener` | Event subscriber | `WorkflowNotificationListener` |

## Action names are snake_case verbs

Every workflow action name (the `string $action` passed to `TransitionRule`/`TransitionWorkflowCommand`) is lower\_snake\_case and verb-first: `request_documents`, `verify_documents`, `request_payment`, `confirm_payment`, `start_quality_review`, `approve`, `archive`, `cancel`, `reject`. `CompanyRegistrationDefinition` exposes each as an `ACTION_*` class constant (e.g. `ACTION_VERIFY_DOCUMENTS = 'verify_documents'`) so callers never hardcode the string literal.

## Status names are PascalCase enum cases, snake_case values

`WorkflowStatus` cases use PascalCase (`PendingDocuments`, `DocumentsVerified`) with lower\_snake\_case backing string values (`pending_documents`, `documents_verified`) — the PascalCase case name is used in PHP code and comparisons; the snake_case value is what is actually persisted to the database and returned over the REST API.

## Capability strings use dot notation

`workflow.view`, `workflow.manage`, `workflow.transition` — a `<module>.<action>` convention distinct from WordPress's own space-free native capabilities (`manage_options`), making it visually obvious at a glance which capabilities are platform-introduced versus WordPress-native.

## Structured log event names

Log entries use a `<namespace>_<domain>.<event>` convention: `bizupkeep_workflow.created`, `bizupkeep_workflow.transitioned`, `bizupkeep_workflow.rolled_back`, `bizupkeep_workflow.unexpected_error`. See `Logging-Standards.md`.

## Database identifiers

Tables: `bizhub_workflow_instances`, `bizhub_workflow_transitions` (prefixed with WordPress's own `$wpdb->prefix` at runtime) — note the `bizhub_` prefix rather than `bizupkeep_workflow_`, matching the convention BizHub itself uses for module-owned tables. Columns are lower\_snake\_case throughout (`workflow_type`, `subject_uuid`, `created_by`, `occurred_at`).
