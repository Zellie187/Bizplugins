# Workflow State Diagrams

Mermaid state diagrams for the one implemented workflow type and a sample of the specified-but-unbuilt ones. See each workflow's own document in this directory for full Input/Validation/Preconditions/Business Rules/Events/Notifications/Rollback/Completion/Audit detail.

## Company Registration — implemented

Reproduced from `Company-Registration.md`; the authoritative source is `CompanyRegistrationDefinition::transitionRules()`.

```mermaid
stateDiagram-v2
    [*] --> Created
    Created --> PendingDocuments: request_documents
    PendingDocuments --> DocumentsVerified: verify_documents
    DocumentsVerified --> AwaitingPayment: request_payment
    AwaitingPayment --> Processing: confirm_payment
    Processing --> QualityReview: start_quality_review
    QualityReview --> Completed: approve
    QualityReview --> Rejected: reject
    Completed --> Archived: archive
    Created --> Cancelled: cancel
    PendingDocuments --> Cancelled: cancel
    DocumentsVerified --> Cancelled: cancel
    AwaitingPayment --> Cancelled: cancel
    Processing --> Cancelled: cancel
    QualityReview --> Cancelled: cancel
    Archived --> [*]
    Cancelled --> [*]
    Rejected --> [*]
```

## Director Changes — specified, not implemented

Per `Director-Changes.md`'s proposed lifecycle.

```mermaid
stateDiagram-v2
    [*] --> Created
    Created --> PendingDocuments: request_documents
    PendingDocuments --> DocumentsVerified: verify_documents
    DocumentsVerified --> Processing: submit_to_cipc
    Processing --> QualityReview: cipc_acknowledged
    QualityReview --> Completed: approve
    QualityReview --> Rejected: reject
    Completed --> Archived: archive
    Created --> Cancelled: cancel
    PendingDocuments --> Cancelled: cancel
    DocumentsVerified --> Cancelled: cancel
    Archived --> [*]
    Cancelled --> [*]
    Rejected --> [*]
```

## Tax Registration — specified, not implemented

Per `Tax-Registration.md`'s proposed lifecycle.

```mermaid
stateDiagram-v2
    [*] --> Created
    Created --> PendingDocuments: request_documents
    PendingDocuments --> DocumentsVerified: verify_documents
    DocumentsVerified --> Processing: submit_to_sars
    Processing --> QualityReview: reference_number_pending
    QualityReview --> Completed: confirm_reference_number
    Completed --> Archived: archive
    Created --> Cancelled: cancel
    PendingDocuments --> Cancelled: cancel
    DocumentsVerified --> Cancelled: cancel
    Archived --> [*]
    Cancelled --> [*]
```

## Approval Workflow — specified, not implemented

Per `Approval-Workflow.md`'s proposed lifecycle — notably shorter, since it models a single sign-off decision rather than a multi-stage filing process.

```mermaid
stateDiagram-v2
    [*] --> Created
    Created --> QualityReview: begin_review
    QualityReview --> Completed: approve
    QualityReview --> Rejected: reject
    Created --> Cancelled: cancel
    QualityReview --> Cancelled: cancel
    Completed --> [*]
    Rejected --> [*]
    Cancelled --> [*]
```

## Payment Workflow — specified, not implemented

Per `Payment-Workflow.md`'s proposed lifecycle — note the extra `QualityReview` (reconciliation) step distinguishing "payment reference supplied" from "payment actually cleared," a distinction Company Registration's simpler `confirm_payment` guard currently collapses into one step.

```mermaid
stateDiagram-v2
    [*] --> Created
    Created --> AwaitingPayment: raise_invoice
    AwaitingPayment --> Processing: supply_payment_reference
    Processing --> QualityReview: begin_reconciliation
    QualityReview --> Completed: confirm_reconciled
    Completed --> Archived: archive
    Created --> Cancelled: cancel
    AwaitingPayment --> Cancelled: cancel
    Processing --> Cancelled: cancel
    Archived --> [*]
    Cancelled --> [*]
```

## Reading these diagrams

Every diagram here uses the same ten `WorkflowStatus` enum cases the engine actually defines (see `docs/architecture/State-Machine.md`) — no specified workflow introduces a new status value, since the engine's `WorkflowStatus` enum is shared across every workflow type by design. Action names on specified (unbuilt) diagrams are illustrative proposals, not yet-defined `ACTION_*` constants — only Company Registration's action names are real, copied verbatim from `CompanyRegistrationDefinition`.
