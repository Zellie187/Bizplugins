# Audit Trail

Two independent mechanisms together form this plugin's audit story: structured application logs (operational, for near-real-time visibility and debugging) and the `bizhub_workflow_transitions` table (durable, for answering "what happened to this workflow, ever," including from the REST API and admin screen).

## Structured log entries

Every log call goes through BizHub's shared `Logger`, using a `bizupkeep_workflow.<event>` naming convention (see `docs/development/Logging-Standards.md`):

| Event | Level | Source | Fields logged |
|---|---|---|---|
| `bizupkeep_workflow.created` | `info` | `WorkflowManager::create()` | `workflow_uuid`, `workflow_type`, `subject_type`, `subject_uuid`, `status`, `user_id` |
| `bizupkeep_workflow.transitioned` | `info` | `WorkflowManager::transition()` | `workflow_uuid`, `workflow_type`, `action`, `from_status`, `to_status`, `user_id`, `reason` |
| `bizupkeep_workflow.rolled_back` | `warning` | `WorkflowManager::rollback()` | `workflow_uuid`, `workflow_type`, `to_status`, `user_id`, `reason` |
| `bizupkeep_workflow.unexpected_error` | `error` | `CompanyRegistrationController::unexpected()` | `exception` (class name), `message`, `user_id` |

Rollback is logged at `warning` rather than `info` deliberately — it is a normal, supported operation, but one worth a human's attention more readily than a routine forward transition.

## The durable audit trail: `bizhub_workflow_transitions`

Every successful `transition()`/`rollback()` call — not just the log line — writes one row to `bizhub_workflow_transitions` (see `docs/database/Tables.md` for the full column list): `uuid`, `workflow_uuid`, `from_status`, `to_status`, `action`, `actor_id`, `reason`, `context` (JSON), `occurred_at`. This table is append-only: nothing in this codebase ever updates or deletes a transition row after insert, and a rollback does not erase the transition it reverses — it appends a new row (`action = 'rollback'`) recording the reversal itself, so the complete, honest sequence of events (including any rollbacks) is always reconstructable.

This table is what backs `WorkflowRepositoryInterface::history()`, in turn exposed via `GET /company-registrations/{uuid}`'s `history` field and (indirectly, via `summaries()`) the admin screen's list view.

## What is captured per transition

Every transition records **who** (`actor_id`, a WordPress user ID — always the authenticated caller, never a system/service account, since every current code path requires `is_user_logged_in()`), **what changed** (`from_status`/`to_status`/`action`), **why** (`reason`, free text, empty string permitted except on rollback where the REST layer requires it), **when** (`occurred_at`), and **with what supporting data** (`context`, e.g. a payment reference or reviewer name).

## Limitations

There is no tamper-evidence beyond ordinary database access controls (no cryptographic hash-chaining between rows), no separate immutable/write-once storage backend, and no automatic archival/retention policy for old transition rows — see `docs/security/Compliance.md` for how this is positioned against South African recordkeeping expectations.
