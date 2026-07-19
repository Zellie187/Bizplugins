# UIF Registration Workflow

**Status: specified, not yet implemented.** A compliance workflow for registering an employer with the Unemployment Insurance Fund, to be built as `UifRegistrationDefinition`/`Guard`/`Service` under `includes/Workflows/UifRegistration/`. UIF registration is typically pursued alongside PAYE registration (see `PAYE-Registration.md`) since both are triggered by the same event — a company becoming an employer.

**Type identifier (proposed)**: `uif_registration`. **Subject**: `subject_type = 'company'`.

## Input

The company's SARS PAYE reference number (UIF registration with SARS is usually done as a combined employer registration alongside PAYE), employee details, and — separately — registration with the Department of Employment and Labour's UIF system (uFiling) where required.

## Validation

A valid SARS PAYE reference number is present (or being registered concurrently); employee count and remuneration details supplied for contribution calculation.

## Preconditions

The subject company must have a `Completed`/`Archived` PAYE Registration workflow instance on file, or be registering UIF concurrently with it — the Service layer should allow either ordering rather than hard-requiring PAYE to be fully complete first, since SARS's combined employer registration process often processes both together.

## Business Rules

Like PAYE, UIF registration is mandatory once an employment relationship exists — there is no voluntary/compulsory distinction, and no discretionary rejection path from the regulator's side.

## State Changes

Proposed lifecycle: `Created` → `PendingDocuments` (employee/employer details) → `DocumentsVerified` → `Processing` (SARS/Department of Employment and Labour submission) → `Completed` → `Archived`; `Cancelled` from any non-terminal status.

## Events Raised

Standard five engine events.

## Notifications

Notify the company contact once the UIF reference number is confirmed.

## Rollback Behaviour

Single-step rollback, unavailable once `Processing` (actual submission) has occurred.

## Completion Criteria

`Completed`: a confirmed UIF reference number is on file and the employer can begin monthly UIF contribution declarations/payments.

## Audit Logging

Standard engine logging; `context` should capture the UIF reference number once issued and the effective registration date.
