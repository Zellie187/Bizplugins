# Workflow Board

**Status: not implemented.** No kanban-style/board UI exists in this plugin. This is a design brief for a visual, per-status board view of workflow instances, a natural companion to the flat list table `Admin.md` describes.

## What it should do

Present workflow instances as cards grouped into columns by `WorkflowStatus`, so a staff user can see, at a glance, how many Company Registrations are sitting in each stage of the lifecycle (`Created`, `Pending Documents`, `Documents Verified`, `Awaiting Payment`, `Processing`, `Quality Review`, `Completed`, `Archived`) and drag/act on a card to advance it — the visual equivalent of `WorkflowStateMachine::allowedActions()`, which already computes exactly which actions are valid from a given status and exists specifically to drive this kind of UI decision (its docblock says as much: "used to drive which buttons a UI shows").

- **Columns** — one per non-terminal-and-relevant `WorkflowStatus` case, populated from `WorkflowRepositoryInterface::summaries('company_registration')` grouped client-side (or server-side, via a new aggregate repository method) by `status`.
- **Card actions** — each card would expose only the actions `WorkflowStateMachine::allowedActions($definition, $currentStatus)` returns for that instance's current status, calling the existing `POST .../actions` endpoint — no new transition logic, purely a visual front-end onto the already-implemented engine.
- **Terminal states** — `Cancelled`/`Rejected`/`Archived` instances would likely collapse into a separate "closed" view rather than cluttering the active board, since `allowedActions()` returns an empty array for terminal statuses (there is nothing actionable to show).

## Which REST endpoints/capabilities it would use

`GET /company-registrations/{uuid}` for card detail, `POST /company-registrations/{uuid}/actions` for drag-driven transitions, both already implemented and requiring `workflow.view`/`workflow.transition` respectively. A board view listing *all* instances would need a new list endpoint, since none exists today (see `docs/api/REST.md`).

## Open design questions

Whether "allowed actions" should be a new field returned by the API (e.g. `present()` gaining an `allowed_actions` array computed via `WorkflowStateMachine::allowedActions()`) so a front-end never has to reimplement that logic itself, versus the front-end calling a dedicated endpoint for it — no such field or endpoint exists today.
