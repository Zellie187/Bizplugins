# Director Changes Workflow

**Status: superseded by the combined Company Amendment workflow.** Rather than a standalone `director_changes` workflow type, director changes are now implemented as one of the amendment types a client can select (alongside Name and Address changes, in any combination) within `CompanyAmendmentDefinition`/`Guard`/`Service` (type `company_amendment`, under `includes/Workflows/CompanyAmendment/`) — see `ROADMAP.md` for why. This document's Input/Validation/Business Rules sections below remain the source of truth for director-change-specific business rules (e.g. "at least one director must remain"); `CompanyAmendmentGuard` enforces the presence of `director_changes` data when `director` is among the workflow's `amendment_types`, but does not yet enforce every rule below (e.g. the "last remaining director" check) — that belongs in a future pass once `BizHub\Companies\Entities\Director` gains the contact/address fields the client-facing form needs.

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
