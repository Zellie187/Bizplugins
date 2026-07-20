# Annual Returns Workflow

**Status: implemented.** Built as `AnnualReturnDefinition`/`Guard`/`Service`/`ServiceProvider` (type `annual_return`) under `includes/Workflows/AnnualReturn/`, following this document's proposed lifecycle exactly (no PendingDocuments/DocumentsVerified stage, no Reject path). `AnnualReturnService::start()` additionally enforces the "not already filed" rule below: at most one non-cancelled Annual Return per company per financial year. Not yet built: REST controller/routes, the due-date/reminder automation described in Business Rules and Notifications (blocked on the queue/automation architecture referenced there), and admin/client-facing UI.

**Type identifier (proposed)**: `annual_returns`. **Subject**: `subject_type = 'company'`.

## Input

The financial year being filed for, confirmation of current director/registered-address details (an Annual Return reaffirms these are still correct, or triggers a separate Director/Address Change workflow if not), and the CIPC filing fee payment.

## Validation

The financial year is not already filed (no duplicate Annual Return for the same company + year); the company's CIPC compliance status permits filing (not already deregistered/in default).

## Preconditions

The company must have an existing, completed registration; typically becomes due annually from the company's registration anniversary month.

## Business Rules

CIPC applies escalating non-compliance consequences (and eventually deregistration risk) for late Annual Returns — this workflow's design should record a `due_date`/`filed_date` pair in `metadata` so downstream automation (once `docs/architecture/Automation-Architecture.md`'s automation engine exists) can flag overdue instances, even though no automatic reminder exists yet.

## State Changes

Proposed lifecycle: `Created` (due date reached) → `AwaitingPayment` (CIPC filing fee) → `Processing` (submitted to CIPC) → `QualityReview` (CIPC acknowledgement pending) → `Completed` → `Archived`; `Cancelled` only in narrow cases (e.g. the company is being deregistered instead of filing); no `Rejected` path anticipated, since CIPC does not typically "reject" a compliant Annual Return the way it might reject a name change.

## Events Raised

Standard five engine events.

## Notifications

Reminder notification as the due date approaches (once automation exists — see `docs/architecture/Automation-Architecture.md`); confirmation notification once CIPC acknowledges the filing.

## Rollback Behaviour

Single-step rollback; realistically only useful before `Processing` (i.e. before the actual CIPC submission), consistent with the caution in `Director-Changes.md`.

## Completion Criteria

`Completed`: CIPC has acknowledged the Annual Return as filed for the given financial year.

## Audit Logging

Standard engine logging; `context` should capture the financial year and filing reference number, since a business may need to demonstrate a specific year's return was filed on time during a compliance review.
