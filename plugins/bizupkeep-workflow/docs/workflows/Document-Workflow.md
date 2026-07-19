# Document Workflow

**Status: specified, not yet implemented.** Unlike the compliance-specific workflows above, this is a proposed **generic, reusable** workflow type for tracking a document's own review/approval lifecycle (e.g. a supporting document uploaded against any other workflow instance), to be built as `DocumentWorkflowDefinition`/`Guard`/`Service` under `includes/Workflows/Document/`. It is intended to compose with other workflow types (e.g. Company Registration's `PendingDocuments` status) rather than replace their document-handling — a document workflow instance could exist per-document, tracked independently of the parent workflow it supports.

**Type identifier (proposed)**: `document_review`. **Subject**: `subject_type = 'document'`, `subject_uuid` = the document's UUID, most likely owned by BizHub's existing `BizHub\Documents` module.

## Input

The uploaded document's reference (from `BizHub\Documents`), the document type/category, and the parent workflow instance UUID it supports (if any), stored in `metadata` rather than as a first-class engine concept.

## Validation

The document reference exists and is readable; the document type is one the parent workflow expects.

## Preconditions

None to start — a document review instance is typically created the moment a document is uploaded.

## Business Rules

A document can be rejected and re-submitted any number of times before being verified — the state graph should allow cycling back from a rejected review to a re-submitted state, unlike Company Registration's guard preconditions which only ever move forward once satisfied.

## State Changes

Proposed lifecycle: `Created` (uploaded) → `PendingDocuments`(renamed conceptually to "pending review") → `QualityReview` → `Completed` (verified) or `Rejected` (needs re-submission, looping back to a fresh instance or a re-open action); `Cancelled` if the parent workflow itself is cancelled.

## Events Raised

Standard five engine events; a listener on `WorkflowCompleted` for this workflow type could plausibly notify the *parent* workflow that a required document is now verified — a cross-workflow-type event reaction not yet designed.

## Notifications

Notify the uploader on rejection (with a reason) and on verification.

## Rollback Behaviour

Single-step rollback, primarily useful for correcting an erroneous verification/rejection decision.

## Completion Criteria

`Completed`: the document has been reviewed and verified as satisfying its purpose.

## Audit Logging

Standard engine logging; `context` should capture the document reference and reviewer identity for every transition.
