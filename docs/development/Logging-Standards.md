# Logging Standards

## One shared logger, structured context arrays

This plugin never builds its own logging mechanism. `WorkflowManager` and `CompanyRegistrationController` both depend on BizHub's shared `BizHub\Framework\Logging\Logger`, and every call site uses the same two-argument shape: a short, dot-namespaced event string, followed by a flat associative array of context:

```php
$this->logger->info('bizupkeep_workflow.created', [
    'workflow_uuid' => $workflow->getUuid(),
    'workflow_type' => $workflow->getWorkflowType(),
    'subject_type' => $workflow->getSubjectType(),
    'subject_uuid' => $workflow->getSubjectUuid(),
    'status' => $workflow->getStatus()->value,
    'user_id' => $command->createdBy,
]);
```

## Event name convention

`bizupkeep_workflow.<event>` — see `Naming-Conventions.md`. The four events actually logged today: `bizupkeep_workflow.created`, `bizupkeep_workflow.transitioned`, `bizupkeep_workflow.rolled_back` (all from `WorkflowManager`, at `info`/`info`/`warning` level respectively), and `bizupkeep_workflow.unexpected_error` (from `CompanyRegistrationController::unexpected()`, at `error` level). See `docs/security/Audit.md` for the full field-by-field breakdown of what each entry logs.

## Log level discipline

- **`info`** — normal, expected lifecycle events (creation, a successful transition).
- **`warning`** — an unusual-but-valid operation worth a human's attention (rollback).
- **`error`** — an unexpected, unhandled exception (`unexpected()`), always paired with the exception's class name and message so it is diagnosable server-side without ever exposing that detail to the API caller.

There is no `debug`-level logging anywhere in this plugin's business logic; verbosity is kept deliberately low and structured.

## Never log secrets, always log identifiers

Log context always includes the actor (`user_id`) and the subject (`workflow_uuid`, `workflow_type`), enabling every log line to answer "who did what, to which workflow, and when" without a separate correlation step. No log call in this codebase ever includes freeform request bodies, tokens, or anything from `Encryption.md`'s "sensitive data" category — only the specific, named fields relevant to that event.

## Logging is not a substitute for the audit trail

Structured logs are operational/diagnostic; the `bizhub_workflow_transitions` table (see `docs/database/Tables.md`) is this plugin's durable, queryable audit trail of record. A transition is always written to both, but they serve different purposes: logs are for operators watching the system in (near) real time or debugging an incident; the transitions table is for answering "show me everything that ever happened to this workflow instance" long after the fact, including from the admin screen and the REST API's `history` payload.

## Adding a new logged event

Follow the existing three-part shape exactly: a `bizupkeep_workflow.<new_event>` name, a flat context array of already-named fields (never nest arbitrary objects — extract the specific scalar fields a log consumer needs), and a level chosen per the discipline above.
