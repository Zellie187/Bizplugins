# Name Changes Workflow

**Status: specified, not yet implemented.** A CIPC-facing workflow for changing a registered company's name, to be built as `NameChangesDefinition`/`Guard`/`Service` under `includes/Workflows/NameChanges/`.

**Type identifier (proposed)**: `name_changes`. **Subject**: `subject_type = 'company'`.

## Input

The proposed new company name (and, per CIPC practice, one or more alternative names in case of a name-reservation clash), plus a special/ordinary resolution authorizing the change.

## Validation

Proposed name(s) meet CIPC naming convention rules (no reserved words without consent, must include a company-type suffix such as "(Pty) Ltd"); a resolution document is attached.

## Preconditions

The company must have an existing, completed registration; the proposed name must not already be reserved or registered by another entity — checked via a CIPC name-availability lookup, which this workflow's Service layer would need to call out to (not the engine's concern).

## Business Rules

A name change only becomes final once CIPC issues an updated registration certificate reflecting the new name — until then, the company's legal name for all other purposes remains the old one, meaning this workflow's `Completed` status is meaningfully distinct from "name change requested."

## State Changes

Proposed lifecycle: `Created` → `PendingDocuments` (resolution + proposed names) → `DocumentsVerified` → `AwaitingPayment` (CIPC name-change filing fee) → `Processing` (submitted to CIPC) → `QualityReview` (name reservation/availability confirmed) → `Completed` → `Archived`; `Cancelled` from any non-terminal status; `Rejected` from `QualityReview` (name unavailable/non-compliant).

## Events Raised

Standard five engine events.

## Notifications

Notify the company contact on submission, on name-availability confirmation or rejection, and on final CIPC approval.

## Rollback Behaviour

Single-step rollback, disallowed once the CIPC filing (`Processing`) has actually been submitted externally.

## Completion Criteria

`Completed`: CIPC has issued an updated registration certificate reflecting the new company name.

## Audit Logging

Standard engine logging; `context` should record both the old and new company name on the transition that finalizes the change, since this is precisely the kind of fact a compliance review would need to reconstruct later (see `docs/security/Compliance.md`).
