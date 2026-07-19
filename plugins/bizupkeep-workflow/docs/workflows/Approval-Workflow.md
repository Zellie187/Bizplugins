# Approval Workflow

**Status: specified, not yet implemented.** A proposed **generic, reusable** workflow type modeling a staff sign-off/approval step that any other workflow type could delegate to, rather than every workflow type re-implementing its own ad-hoc "quality review" logic the way Company Registration's `QualityReview` status currently does inline. To be built as `ApprovalWorkflowDefinition`/`Guard`/`Service` under `includes/Workflows/Approval/`.

**Type identifier (proposed)**: `approval`. **Subject**: `subject_type` = whatever the parent workflow's own subject type is (e.g. `company`), so an approval instance is traceable back to the same business entity its parent concerns.

## Input

The item requiring approval (a reference to the parent workflow instance and the specific action it is gating), the reviewer(s) eligible to approve, and any supporting context the reviewer needs.

## Validation

The parent workflow reference is valid and currently in a state that actually needs this approval.

## Preconditions

None to start.

## Business Rules

An approval must record who approved it (mirroring `CompanyRegistrationGuard::guardApprove()`'s `reviewed_by` precondition, generalized here as this workflow type's own core purpose rather than a bolt-on guard on someone else's workflow) — a generic Approval workflow's entire reason for existing is to make that precondition, and its associated audit trail, reusable rather than reimplemented per parent workflow.

## State Changes

Proposed lifecycle: `Created` (approval requested) → `QualityReview` (under review) → `Completed` (approved) or `Rejected` (declined, with a reason); `Cancelled` if the parent workflow's need for approval evaporates (e.g. the parent itself is cancelled).

## Events Raised

Standard five engine events; `WorkflowCompleted`/`WorkflowCancelled` here are the natural signal for the parent workflow to proceed or halt — a cross-workflow-type reaction not yet designed (see `Document-Workflow.md`).

## Notifications

Notify the requester once a decision (`Completed`/`Rejected`) is made; notify the assigned reviewer(s) when a new approval is requested.

## Rollback Behaviour

Single-step rollback, for correcting an erroneous approval/rejection decision before the parent workflow has acted on it.

## Completion Criteria

`Completed`: an eligible reviewer has explicitly approved, recording their identity and (optionally) comments.

## Audit Logging

Standard engine logging; `context` should capture the reviewer identity and any comments for every transition — this workflow type's audit trail is, in effect, its entire product.
