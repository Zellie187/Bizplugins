# Dashboard

**Status: not implemented.** This plugin ships no dashboard widget or summary view beyond the single flat list table in `Admin.md`. This is a design brief for a future dashboard surface, most plausibly integrating with BizHub's existing `BizHub\Dashboard` module rather than this plugin building a competing dashboard framework.

## What it should do

Give a staff user (manager/admin) an at-a-glance view of workflow activity across the platform: counts of instances per `WorkflowStatus`, a "stuck" indicator (instances that have not transitioned in an unusually long time relative to their status), and recent activity (the most recent transitions across all instances, not just one workflow).

- **Status breakdown** — a count of Company Registration instances per status, sourced from `WorkflowRepositoryInterface::summaries()` (or a new, purpose-built aggregate query — `summaries()` today returns full row-per-instance data, not pre-aggregated counts, so a genuine dashboard would likely need a new repository method, e.g. `countsByStatus(string $workflowType): array<string,int>`).
- **Recent activity feed** — the most recent N rows from `bizhub_workflow_transitions` across all instances, which no current repository method exposes (`history()` is scoped to a single `workflow_uuid`); this would also be a new method.
- **Multi-workflow-type awareness** — once additional workflow types beyond Company Registration are implemented (see `docs/workflows/`), the dashboard should aggregate across all registered types, not assume Company Registration is the only one, unlike the current admin screen.

## Which REST endpoints/capabilities it would use

No current REST endpoint returns aggregate/cross-instance data — the closest thing (`WorkflowAdminMenu`) reads directly from the repository inside `wp-admin`, not via REST. A REST-backed dashboard (e.g. for a future single-page-app admin experience) would need new endpoints, and would be gated by `workflow.view` at minimum, likely with a `workflow.manage`-gated variant for anything mutable.

## Open design questions

Whether this belongs as a standalone screen in this plugin, a widget contributed into BizHub's own `BizHub\Dashboard` module, or both, is undecided. No aggregate query methods exist on `WorkflowRepositoryInterface` today — any dashboard work should extend that interface deliberately rather than having a dashboard-specific class run ad-hoc SQL.
