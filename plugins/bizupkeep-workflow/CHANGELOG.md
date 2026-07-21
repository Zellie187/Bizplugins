# Changelog

All notable changes to BizUpKeep Workflow are documented in this file.

## [1.2.0] - 2026-07-21

### Added

- **Quality Review now covers all three workflow types**, not just Company Registration: Company Amendment and Annual Return applications sitting in `QualityReview` now appear in the queue, with type-specific detail rendering (requested amendment types/proposed names/director changes/new address for an Amendment; financial year for an Annual Return) and Approve/Reject. Annual Return has no Reject action in its state machine, so its review form only offers Approve.
- **`WorkflowTypeServiceInterface`**: the common `performAction()`/`rollback()`/`find()`/`historyFor()` shape every workflow type's Service class already had, now named as an interface (`CompanyRegistrationService`, `CompanyAmendmentService`, `AnnualReturnService` all implement it) so code like Quality Review can dispatch to the right concrete service for a given workflow type without hardcoding one.
- **`WorkflowRepositoryInterface::summariesByStatus()`**: filters by status at the database level (unlike `summaries()` + a client-side filter, which paginates before filtering and could silently miss matching rows past the scan limit). Quality Review's queue now uses this instead of scanning-then-filtering.
- The "Workflows" admin list (`WorkflowAdminMenu`) also now lists all three types with a Type column, instead of only Company Registration; its permission check was also fixed from a hardcoded `manage_options` to `Capabilities::WORKFLOW_VIEW`, consistent with every other access point (REST controller, Quality Review).
- 2 new PHPUnit tests for `summariesByStatus()` (50 total, up from 48), all passing; PHPStan and PHPCS clean.

## [1.1.0] - 2026-07-21

### Added

- **Company Amendment workflow**: `CompanyAmendmentDefinition`/`Guard`/`Service`/`ServiceProvider` (type `company_amendment`) — a single application covering Director, Name, and/or registered-Address changes in any combination, matching the product catalogue's combined SKUs. See `ROADMAP.md` for how this departs from the three separate workflow types originally proposed in `docs/workflows/Director-Changes.md`/`Name-Changes.md`/`Address-Changes.md`.
- **Annual Return workflow**: `AnnualReturnDefinition`/`Guard`/`Service`/`ServiceProvider` (type `annual_return`), implemented per `docs/workflows/Annual-Returns.md`, including a duplicate-filing guard (one non-cancelled Annual Return per company per financial year).
- **Quality Review admin screen**: a staff-facing submenu under BizHub's admin menu listing every Company Registration application in `QualityReview`, with Approve/Reject actions and document download.
- `CompanyRegistrationService::start()` now accepts an optional `$metadata` array (e.g. `proposed_names`), matching the pattern the two new workflow types already use - additive, existing 2-argument callers are unaffected.
- Client-facing intake for all three workflow types is now live in the `astra-child` theme's `/apply/` page (single-page, JS-toggled by application type) as of theme v1.8.0 - see that theme's changelog. This plugin's REST controllers/routes for Company Amendment and Annual Return remain deliberately unbuilt (the theme calls the services directly via the shared container, the same pattern Company Registration's intake already used).
- 16 new PHPUnit tests covering both new workflow guards (48 total, up from 32), all passing; PHPStan level 6 and PHPCS clean.

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
