# Seeders

**There are no database seeders in this codebase.** No `Seeder` class, no `wp-cli` seed command, and no fixture-loading script exists anywhere in this plugin. This is stated plainly rather than as a placeholder describing imaginary tooling.

## What a seeder would need to do, if built for local development

A local-dev seeder for this plugin would need to:

1. Depend on BizHub's `DatabaseInterface` and this plugin's `WorkflowRepositoryInterface` exactly the way `WorkflowManager` and its tests already do — never write raw `INSERT` SQL against `bizhub_workflow_instances`/`bizhub_workflow_transitions` directly, to avoid duplicating (and risking drifting from) the hydration logic in `WorkflowRepository`.
2. Create instances through the real engine, not by fabricating rows — i.e. call `WorkflowEngineInterface::create()` and `::transition()` with realistic `CreateWorkflowCommand`/`TransitionWorkflowCommand` sequences (following `CompanyRegistrationDefinition`'s actual transition graph), so seeded data is guaranteed structurally valid and exercises the same guards real usage would.
3. Depend on a seeded `BizHub\Companies` company already existing (since `CompanyRegistrationService::start()` requires a real company UUID via `CompanyServiceInterface::getCompany()`), meaning a workflow seeder would need to run after — or alongside — a BizHub-level Companies seeder.
4. Be gated so it can never run against a production database — e.g. a WP-CLI command guarded by `WP_DEBUG` or an explicit `--force` flag, following whatever convention BizHub itself adopts for its own module seeders, if any.
5. Cover a representative spread of statuses (not just `Created`), so admin-screen and list-view development/testing has something realistic to look at across the full `WorkflowStatus` lifecycle, including at least one cancelled and one rolled-back example.

Until such a seeder exists, local development and manual testing of the admin screen (`WorkflowAdminMenu`) requires creating workflow instances the same way production would: through the real REST API (`POST /company-registrations`) or a WP-CLI/PHP console call into the container-resolved `CompanyRegistrationService`.
