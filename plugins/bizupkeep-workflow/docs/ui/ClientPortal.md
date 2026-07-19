# Client Portal

**Status: not implemented.** No client-facing UI exists in this plugin today — only the single admin-only list screen described in `Admin.md`. This is a design brief for what a client-facing portal view of a company's own workflows should do, should one be built (likely in BizHub's existing `BizHub\ClientPortal` module rather than in this plugin, given that module already owns client-facing surfaces platform-wide).

## What it should do

Let an authenticated client (a company's own registered contact, not staff) see the status and history of their own Company Registration (and, eventually, other workflow types) without exposing any other client's data or any internal staff-only detail.

- **List view**: every workflow instance bound to the logged-in client's own company/companies, using `WorkflowRepositoryInterface::findForSubject('company', $companyUuid)` scoped strictly to the requesting client's own `subject_uuid` — never a bare `summaries()` call, which is not client-scoped.
- **Detail view**: a single workflow's current status (rendered via `WorkflowStatus::label()`), a plain-language description of what happens next, and — depending on how much internal detail is appropriate to expose — some or all of its transition history.
- **Action affordances**: for any action a client is permitted to trigger themselves (e.g. none in Company Registration today, since all nine actions require staff-level `workflow.transition`/`workflow.manage`; but a future workflow type's spec might designate a client-triggerable action, e.g. "upload documents"), a form calling the existing `POST .../actions` endpoint.

## Which REST endpoints/capabilities it would use

`GET /company-registrations/{uuid}` for detail (requires `workflow.view` — meaning a client-scoped capability variant, or a dedicated client-portal capability, would likely be needed rather than reusing the staff-oriented `workflow.view` as-is, since that capability is currently granted only to staff/manager/admin roles, not to arbitrary client accounts). No endpoint exists today for "list my own workflows" — that would need a new, client-scoped REST route (or a portal-side call to `findForSubject()` through a purpose-built controller), since the current API has no bulk/list endpoint at all (see `docs/api/REST.md`).

## Open design questions

Whether client-portal capabilities should be new, narrower capability constants (e.g. `workflow.view.own`) distinct from staff's `workflow.view`, and how much transition-history detail (actor names, internal reasons) is appropriate to show a client versus staff, are both undecided. Neither should be assumed answered by this document.
