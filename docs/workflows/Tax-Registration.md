# Tax Registration Workflow

**Status: specified, not yet implemented.** A SARS compliance workflow for registering a newly-formed company for income tax, to be built as `TaxRegistrationDefinition`/`Guard`/`Service` under `includes/Workflows/TaxRegistration/`. This is distinct from, and typically follows, Company Registration (see `Company-Registration.md`) — a company must exist at CIPC before it can be registered for tax at SARS.

**Type identifier (proposed)**: `tax_registration`. **Subject**: `subject_type = 'company'`.

## Input

The company's CIPC registration certificate/number, director and public-officer details (SARS requires a registered public officer for company tax purposes), and banking details for the tax reference.

## Validation

A valid CIPC registration number is present; a public officer is nominated with a valid SA ID number.

## Preconditions

The subject company must have a `Completed` (or `Archived`) Company Registration workflow instance — this workflow's Service layer should check for this via `WorkflowRepositoryInterface::findForSubject('company', $companyUuid)` before allowing `create()`, rather than duplicating that check inside the engine.

## Business Rules

SARS typically auto-registers a new CIPC-registered company for income tax without a separate application in many cases — this workflow models the *confirmation/follow-up* process (obtaining the tax reference number and registering for eFiling) rather than an from-scratch application, since the underlying registration may already exist by the time this workflow starts.

## State Changes

Proposed lifecycle: `Created` → `PendingDocuments` (public officer details, CIPC certificate) → `DocumentsVerified` → `Processing` (SARS eFiling registration/confirmation) → `QualityReview` (tax reference number confirmed) → `Completed` → `Archived`; `Cancelled` from any non-terminal status.

## Events Raised

Standard five engine events.

## Notifications

Notify the company contact once the SARS tax reference number is confirmed.

## Rollback Behaviour

Single-step rollback, unavailable once the SARS-facing step (`Processing`) has been submitted.

## Completion Criteria

`Completed`: a confirmed SARS income tax reference number is on file and eFiling access is provisioned.

## Audit Logging

Standard engine logging; `context` should capture the SARS tax reference number once confirmed — this is the single most important fact this workflow produces and other workflows (VAT/PAYE/UIF Registration) are likely to depend on it existing.
