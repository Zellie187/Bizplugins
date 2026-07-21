# Changelog

All notable changes to BizUpKeep Workflow are documented in this file.

## [1.6.0] - 2026-07-21

### Added

- **Annual Return applications can now cover multiple financial years at once**: `AnnualReturnService::start()` takes `$filings` (a list of `{financial_year, turnover}` pairs) instead of a single `int $financialYear` - a client behind on several years' filings pays for all of them together, not as separate applications. Turnover is asked for per year since CIPC's filing fee is turnover-banded, and is shown to staff (alongside financial year) on Quality Review to help them work out the right quote.
- `AnnualReturnService::alreadyFiled()` (now `alreadyFiledYears()`) checks every requested year against every one of the company's existing, non-cancelled Annual Return workflows, each of which may itself cover several years - old single-`financial_year` metadata (from before this shape existed) is read as a one-entry list, so historical workflows are still counted correctly.
- **Revise Quote**: a new `revise_quote` action (a self-loop, `AwaitingPayment -> AwaitingPayment`) closes the gap flagged in 1.5.0's changelog - staff can now correct a wrong quote amount/notes before the client pays, via the same "Send Quote" form on Quality Review (which now also renders, pre-filled with the current amount/notes, while `AwaitingPayment`, not just `Created`). Guarded by the same `quote_amount > 0` precondition as `request_payment` (`AnnualReturnGuard::guardQuoteAmount()`, renamed from `guardRequestPayment()` since it now serves both actions). Which action actually fires is derived server-side from the workflow's current status, not trusted from the submitted form.
- 8 new PHPUnit tests (2 guard, 6 in a new `AnnualReturnServiceTest.php` covering multi-year filings, duplicate-year detection including the backward-compatible old-shape check, and the revise-quote lifecycle), 69 total (up from 61); PHPStan and PHPCS clean.

## [1.5.0] - 2026-07-21

### Changed

- **Annual Return lifecycle redesign**: `request_payment` (Created -> AwaitingPayment) is no longer something the client's own submission fires automatically - `AnnualReturnGuard::guardRequestPayment()` now requires a `quote_amount` (a positive number) in context, so the transition can only happen once staff have actually checked CIPC and decided what to charge. This finally implements the workflow spec's "staff to check annual returns on CIPC site >> send quote to client" step, which the original 1.0.0 implementation skipped entirely (payment used to fire immediately at submission, for whatever fixed-price product the client happened to buy).
- Quality Review gained a "Send Quote" form, shown only for an Annual Return application sitting in `Created`: staff enter an amount (ZAR) and optional notes, which fires `request_payment` with `quote_amount`/`quote_notes` in context - both persisted in the workflow's metadata and shown back on future visits (alongside the client's own `client_notes`, captured at submission and displayed for staff before they quote).
- `AnnualReturnService::start()` gained an optional `$metadata` parameter (matching the pattern `CompanyRegistrationService::start()` already established), used to carry the client's optional submission notes now that nothing else transitions - and so records a reason - at submission time.
- 6 new PHPUnit tests (4 guard, plus a new integration test file with 2 tests covering the precondition and the full quote-to-completed lifecycle), 61 total (up from 55); PHPStan and PHPCS clean.

## [1.4.0] - 2026-07-21

### Added

- **Staff-side document upload**: Quality Review's detail view (`?page=bizhub-quality-review&workflow=...`) now renders regardless of the workflow's current status - previously it only rendered anything beyond a "no longer awaiting review" notice while the application was actually in `QualityReview`, meaning staff had no way to reach a Completed (or any other status) application's company/document details at all. The Approve/Reject decision form is still shown only while the status is `QualityReview`; everything else (company details, requested changes, submitted documents, and the new upload form) now renders for any status.
- A new upload form on that same page lets staff attach a document, in any `DocumentCategory`, to the application's company - e.g. the final CIPC registration/amendment certificate once approved. It reuses `DocumentService::uploadDocument()` directly (own nonce/capability check, gated by the existing `Capabilities::WORKFLOW_TRANSITION`, 10MB/PDF-JPG-PNG validation mirroring the client-facing upload form's own limits). No client-side change was needed: My Documents already lists every document for a company regardless of category, so a staff upload appears there automatically - this is what makes "download registration/amendment documents from the portal" (from the original workflow spec) actually work end-to-end for the first time.
- The "Workflows" admin list (`WorkflowAdminMenu`) gained a "View" link per row, linking into Quality Review's detail view - previously that list had no links anywhere, so a non-QualityReview workflow's detail page (and now its document upload form) was unreachable except by typing the UUID into the URL by hand.
- Verified end-to-end: uploaded a document to a Completed Company Registration application from the admin side and confirmed it appeared correctly on the client's My Documents page with the right category; confirmed a forged nonce is rejected.
- 55 passing PHPUnit tests (unchanged - this is UI-layer work with no new state-machine behavior), PHPStan clean.

## [1.3.0] - 2026-07-21

### Added

- **Company Registration name-rejection loop**: a new non-terminal `WorkflowStatus::NamesRejected`, reachable from `QualityReview` via a new `reject_name` action, distinct from the existing terminal `reject`. A new `resubmit_names` action moves the workflow back to `QualityReview`, overwriting `proposed_names` in its metadata with whatever the client submitted - so when CIPC declines every proposed company name, staff can send the application back for new names without the client having to start a whole new application.
- Quality Review's review form now shows a second reject button ("Reject - Name Not Approved") alongside the existing "Reject", for Company Registration only (Company Amendment and Annual Return still only get the plain terminal Reject).
- `CompanyRegistrationGuard` gained a `resubmit_names` precondition: at least one non-blank proposed name is required in context.
- `WorkflowStatus::NamesRejected` is included in Company Registration's `cancel`-eligible statuses, consistent with every other non-terminal status - a client can still withdraw an application while waiting to resubmit names.
- 5 new PHPUnit tests (3 guard, 2 integration, covering the full reject_name -> resubmit_names -> approve loop and the resubmit precondition), 55 total (up from 50); PHPStan and PHPCS clean.

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
