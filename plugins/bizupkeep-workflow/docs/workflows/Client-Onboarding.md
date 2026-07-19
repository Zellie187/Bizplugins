# Client Onboarding Workflow

**Status: specified, not yet implemented.** This is a design specification for a future workflow type built on the existing engine (`WorkflowEngineInterface`, `WorkflowDefinitionInterface`, `TransitionGuardInterface`), following the five-piece pattern in `docs/development/Workflow-Standards.md`. No `ClientOnboardingDefinition`/`Guard`/`Service` class exists in `includes/Workflows/` today.

**Type identifier (proposed)**: `client_onboarding`. **Subject**: `subject_type = 'client'`, `subject_uuid` = the client's UUID once created in whichever BizUpKeep Core module owns client records.

## Input

New client's legal/contact details (name, ID/company registration number, contact email/phone), the services they wish to onboard for (e.g. company registration, tax registration), and the staff member initiating onboarding.

## Validation

Contact details well-formed (valid email, valid SA ID or company registration number format); at least one requested service selected.

## Preconditions

None to *start* onboarding — this is typically the very first workflow run against a new client relationship.

## Business Rules

A client record must not already exist with the same ID/registration number (duplicate-prevention is a Service-layer concern, checked against BizUpKeep Core's client module, not the engine).

## State Changes

Proposed lifecycle: `Created` → `AwaitingClientDetails` → `DetailsVerified` → `AwaitingClientDocuments`(reuse `PendingDocuments`) → `DocumentsVerified` → `Processing` (setting up internal client record/portal access) → `Completed` → `Archived`, with `Cancelled` available from any non-terminal status (a prospective client withdraws or is declined).

## Events Raised

The five standard engine events (`WorkflowCreated`, `WorkflowTransitioned`, `WorkflowCompleted`, `WorkflowCancelled`, `WorkflowRolledBack`) — no new event types anticipated.

## Notifications

Welcome/confirmation notifications to the client at `Created` and `Completed`; internal staff notification when documents are submitted (`AwaitingClientDocuments` → `DocumentsVerified`).

## Rollback Behaviour

Single-step rollback, consistent with the engine's generic `rollback()` — e.g. reverting a premature "documents verified" mark.

## Completion Criteria

`Completed`: the client is fully onboarded and has portal/service access provisioned; may move to `Archived` once no further onboarding action is needed.

## Audit Logging

Standard engine logging (`bizupkeep_workflow.created`/`.transitioned`/`.rolled_back`) plus the durable `bizhub_workflow_transitions` trail — no additional logging beyond what the engine already provides is anticipated.
