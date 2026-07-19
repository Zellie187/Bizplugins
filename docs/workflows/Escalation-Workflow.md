# Escalation Workflow

**Status: specified, not yet implemented.** A proposed **generic, reusable** workflow type for escalating a stuck or overdue parent workflow instance to a more senior staff member, to be built as `EscalationWorkflowDefinition`/`Guard`/`Service` under `includes/Workflows/Escalation/`. This depends conceptually on the not-yet-built automation engine described in `docs/architecture/Automation-Architecture.md`, since "this workflow has been stuck in one status too long" is a time-based trigger this plugin has no mechanism to detect automatically today.

**Type identifier (proposed)**: `escalation`. **Subject**: `subject_type` = the parent workflow's own subject type, so an escalation is traceable to the same business entity.

## Input

A reference to the parent workflow instance and the status it has been stuck in, how long it has been stuck, and the escalation target (a specific senior staff member or a role, e.g. `bizhub_manager`).

## Validation

The parent workflow reference is valid and genuinely non-terminal (escalating an already-completed or cancelled workflow makes no sense).

## Preconditions

Realistically, this workflow should only ever be created by an automated trigger (once built) or by a staff member manually flagging a stuck case — not by an external API caller directly, since "stuck" is a judgment about elapsed time, not a fact any caller should assert unilaterally without evidence.

## Business Rules

An escalation should not silently swallow the parent workflow's own state — it exists alongside the parent, drawing attention to it, not replacing or pausing it. The parent workflow must remain independently actionable throughout.

## State Changes

Proposed lifecycle: `Created` (escalation raised) → `QualityReview` (senior staff member reviewing) → `Completed` (resolved — the parent workflow was unstuck, or escalation deemed unnecessary) → `Archived`; `Cancelled` if the parent workflow resolves itself independently before the escalation is reviewed.

## Events Raised

Standard five engine events; `WorkflowCreated` here is the natural trigger for an urgent notification to the escalation target, distinct from the calmer notification templates other workflow types use.

## Notifications

Immediate, high-priority notification to the escalation target on `Created`; a resolution notification back to whoever raised the escalation on `Completed`.

## Rollback Behaviour

Single-step rollback, of limited practical value here given escalations are inherently reactive rather than sequential.

## Completion Criteria

`Completed`: the escalation has been reviewed by the designated senior staff member and a resolution (or explicit "no action needed") is recorded.

## Audit Logging

Standard engine logging; `context` should capture which parent workflow/status triggered the escalation and who resolved it, since a pattern of frequent escalations against one workflow type is itself a useful operational signal.
