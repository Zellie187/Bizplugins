# PAYE Registration Workflow

**Status: specified, not yet implemented.** A SARS compliance workflow for registering an employer for Pay-As-You-Earn (employees' tax), to be built as `PayeRegistrationDefinition`/`Guard`/`Service` under `includes/Workflows/PayeRegistration/`.

**Type identifier (proposed)**: `paye_registration`. **Subject**: `subject_type = 'company'`.

## Input

The company's SARS income tax reference number, the date the company first employed staff (SARS requires PAYE registration within a set period of becoming an employer), and details of the first employee(s).

## Validation

A valid SARS income tax reference number is present; at least one employee's details (ID number, start date, remuneration) are supplied.

## Preconditions

The subject company must have a `Completed`/`Archived` Tax Registration workflow instance on file (see `Tax-Registration.md`).

## Business Rules

PAYE registration is legally required once a company becomes an employer, regardless of the number of employees or amounts paid — unlike VAT, there is no voluntary/compulsory distinction to model here; the workflow should treat registration as mandatory once the precondition (an employment relationship exists) is met.

## State Changes

Proposed lifecycle: `Created` → `PendingDocuments` (employee/employer details) → `DocumentsVerified` → `Processing` (SARS EMP101e submission) → `Completed` → `Archived`; `Cancelled` from any non-terminal status. No `QualityReview`/`Rejected` path is anticipated — SARS does not typically decline a mandatory PAYE registration the way it might decline a discretionary VAT one.

## Events Raised

Standard five engine events.

## Notifications

Notify the company contact once the SARS PAYE reference number is issued.

## Rollback Behaviour

Single-step rollback, unavailable once `Processing` (actual SARS submission) has occurred.

## Completion Criteria

`Completed`: a confirmed SARS PAYE reference number is on file, and the company can begin submitting EMP201 monthly employer declarations.

## Audit Logging

Standard engine logging; `context` should capture the PAYE reference number once issued and the effective registration date, since SARS penalties for late PAYE registration are based on that date.
