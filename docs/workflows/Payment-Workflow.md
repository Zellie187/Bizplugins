# Payment Workflow

**Status: specified, not yet implemented.** A proposed **generic, reusable** workflow type for tracking a payment's own lifecycle (invoice raised → paid → reconciled), intended to compose with fee-bearing workflows like Company Registration (which today models payment only as a single `AwaitingPayment` → `Processing` transition guarded by a `payment_reference` string, not as its own tracked entity). To be built as `PaymentWorkflowDefinition`/`Guard`/`Service` under `includes/Workflows/Payment/`.

**Type identifier (proposed)**: `payment`. **Subject**: `subject_type = 'invoice'` (or similar), `subject_uuid` = the invoice/payment record's UUID, most likely owned by a billing module elsewhere in BizUpKeep Core.

## Input

The amount due, the currency (ZAR), the fee purpose (e.g. "CIPC filing fee for Name Change workflow X"), and the parent workflow instance this payment supports.

## Validation

Amount is a positive numeric value; currency is a supported code.

## Preconditions

None to start — created the moment a fee becomes due on any other workflow.

## Business Rules

A payment can be partially reconciled (e.g. an EFT that takes days to clear) — the state graph should distinguish "payment reference supplied" from "payment actually confirmed/reconciled," rather than collapsing both into a single `confirm_payment`-style action the way Company Registration currently does for simplicity.

## State Changes

Proposed lifecycle: `Created` (invoice raised) → `AwaitingPayment` → `Processing` (payment reference supplied, pending clearance) → `QualityReview` (reconciliation check) → `Completed` (confirmed cleared) → `Archived`; `Cancelled` from any non-terminal status (e.g. the parent workflow itself is cancelled before payment).

## Events Raised

Standard five engine events; `WorkflowCompleted` here is the natural trigger for unblocking whatever parent workflow action was waiting on payment — a cross-workflow-type reaction not yet designed (see `Document-Workflow.md`'s equivalent note).

## Notifications

Payment-due reminder, payment-received acknowledgement, and reconciliation-confirmed notification.

## Rollback Behaviour

Single-step rollback, primarily for correcting an erroneously-marked reconciliation.

## Completion Criteria

`Completed`: the payment has cleared and been reconciled against the fee it was raised for.

## Audit Logging

Standard engine logging; `context` should capture the payment reference, amount, and reconciliation reference for every transition — this is a natural candidate for stronger audit rigor than most workflow types, given the financial subject matter (see `docs/security/Compliance.md`).
