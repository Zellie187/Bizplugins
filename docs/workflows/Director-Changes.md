# Director Changes Workflow

**Status: specified, not yet implemented.** A CIPC-facing workflow for adding, removing, or amending a company's registered directors, to be built as `DirectorChangesDefinition`/`DirectorChangesGuard`/`DirectorChangesService` under `includes/Workflows/DirectorChanges/`, following the same five-piece pattern `CompanyRegistrationDefinition` already establishes (see `docs/architecture/Integration-Architecture.md`'s reference to this being the next workflow type to register alongside Company Registration in `bizupkeep-workflow.php`'s `bizhub/register_providers` callback).

**Type identifier (proposed)**: `director_changes`. **Subject**: `subject_type = 'company'`.

## Input

The change type (appoint / resign / amend details), the affected director's identity (SA ID number or passport for foreign directors), and consenting signatures/proof of identity.

## Validation

A valid SA ID number (or passport + nationality) for the director; at least one existing director must remain after a resignation (CIPC requires every company to have at least one director at all times).

## Preconditions

The subject company must already exist and have a completed Company Registration (or an existing CIPC registration record) — this workflow does not create a company, only amends its director records.

## Business Rules

Removing the last remaining director must be rejected before the workflow can reach a submittable state; appointing a director requires their signed consent to act.

## State Changes

Proposed lifecycle: `Created` → `PendingDocuments` (consent/ID collection) → `DocumentsVerified` → `Processing` (CIPC CoR39/CoR40-style filing submitted) → `QualityReview` → `Completed` → `Archived`; `Cancelled` from any non-terminal status; `Rejected` from `QualityReview` (e.g. CIPC rejects the filing).

## Events Raised

Standard five engine events; no new event type anticipated beyond reusing `WorkflowTransitioned` for the CIPC-submission step.

## Notifications

Notify the company's contact when documents are requested, when the change is submitted to CIPC, and on final approval/rejection.

## Rollback Behaviour

Single-step rollback; particular care needed around not allowing rollback once a CIPC filing has actually been submitted externally (a business-rule guard, not an engine limitation) — a guard should refuse `rollback` once `Processing` has been entered, even though the generic engine would otherwise permit it structurally.

## Completion Criteria

`Completed`: CIPC confirms the director change is reflected on the company's record.

## Audit Logging

Standard engine logging, with `context` capturing which director and which change type (appoint/resign/amend) for every transition, mirroring how `CompanyRegistrationGuard` captures `reviewed_by`/`payment_reference`.
