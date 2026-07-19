# Changelog

All notable changes to BizUpKeep Workflow are documented in this file.

## [1.0.0] - 2026-07-19

Initial release.

### Added

- **Workflow engine**: `WorkflowEngineInterface`/`WorkflowManager` providing `create()`, `transition()`, `rollback()`, `find()`, and `historyFor()` against a declarative, per-workflow-type state machine (`WorkflowStateMachine`) and pluggable business-rule guards (`TransitionGuardInterface`). Ten shared lifecycle statuses (`WorkflowStatus`), five dispatched events (`WorkflowCreated`, `WorkflowTransitioned`, `WorkflowCompleted`, `WorkflowCancelled`, `WorkflowRolledBack`), and a structured audit trail (`bizhub_workflow_instances`/`bizhub_workflow_transitions`).
- **Company Registration workflow**: the first concrete workflow type built on the engine — a 9-action, 10-status lifecycle from `Created` through `Archived`, with guarded preconditions on document verification, payment confirmation, and quality-review approval, plus cancellation and rejection paths.
- **REST API**: four endpoints under `bizupkeep-workflow/v1` — start, retrieve, act on, and roll back a Company Registration workflow instance, authenticated via WordPress cookie/nonce and authorized via BizHub's `AuthorizationServiceInterface` against three new capabilities (`workflow.view`, `workflow.manage`, `workflow.transition`).
- **Admin screen**: a read-only Company Registration list view registered as a submenu of BizHub's own admin menu.
- **Notifications**: transition-triggered notifications for seven of Company Registration's nine actions, delivered via BizHub's shared `NotificationQueue`.
- **Integration with BizHub**: contributes into BizHub's single shared DI container and provider/boot lifecycle via the `bizhub/container_definitions` filter and `bizhub/register_providers` action — no second container, database connection, or event dispatcher is created.
- **Dependency enforcement**: `Requires Plugins: bizhub, bizupkeep-core` header plus a runtime `DependencyGuard` that deactivates this plugin with an admin notice if either dependency is missing.
- **Test suite**: 32 passing PHPUnit tests across unit, integration, and full-lifecycle workflow tiers, all clean against PHPStan level 6 and PHPCS (PSR-12 + WordPress security sniffs).

### Not included in this release

15 additional South African compliance workflow types (Director Changes, Address/Name Changes, Annual Returns, Tax/VAT/PAYE/UIF Registration, and others) are specified as implementation-ready design documents under `docs/workflows/` but are not built in this release. A queue/job system, scheduled automation, and any client-facing UI beyond the admin list screen are likewise not part of this release. See `ROADMAP.md`.
