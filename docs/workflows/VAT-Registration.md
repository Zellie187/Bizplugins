# VAT Registration Workflow

**Status: specified, not yet implemented.** A SARS compliance workflow for registering a company for Value-Added Tax, to be built as `VatRegistrationDefinition`/`Guard`/`Service` under `includes/Workflows/VatRegistration/`.

**Type identifier (proposed)**: `vat_registration`. **Subject**: `subject_type = 'company'`.

## Input

The company's SARS income tax reference number, projected/actual taxable turnover (SARS distinguishes compulsory registration above the statutory threshold from voluntary registration below it), and supporting financial records (invoices, contracts) evidencing turnover.

## Validation

A valid SARS income tax reference number is present; turnover figures are numeric and internally consistent with any supporting documents attached.

## Preconditions

The subject company must have a `Completed`/`Archived` Tax Registration workflow instance on file (see `Tax-Registration.md`) — VAT registration presupposes an existing income tax reference.

## Business Rules

Whether registration is compulsory (turnover exceeded the statutory VAT threshold) or voluntary changes which supporting evidence SARS requires — the workflow's guard for `verify_documents` (or an equivalent action) should branch on a `registration_type` context field (`compulsory` vs. `voluntary`) rather than assuming one path.

## State Changes

Proposed lifecycle: `Created` → `PendingDocuments` (turnover evidence) → `DocumentsVerified` → `Processing` (SARS VAT101 submission) → `QualityReview` (SARS decision pending) → `Completed` → `Archived`; `Cancelled` from any non-terminal status; `Rejected` from `QualityReview` (SARS declines voluntary registration, which it may do at its discretion).

## Events Raised

Standard five engine events.

## Notifications

Notify the company contact once the SARS VAT number is issued, or on rejection with SARS's stated reason.

## Rollback Behaviour

Single-step rollback, unavailable once `Processing` (actual SARS submission) has occurred.

## Completion Criteria

`Completed`: a confirmed SARS VAT registration number is on file.

## Audit Logging

Standard engine logging; `context` should capture the registration type (compulsory/voluntary) and the resulting VAT number once issued, since a business may need to prove its VAT registration date for input-tax-claim purposes during a SARS audit.
