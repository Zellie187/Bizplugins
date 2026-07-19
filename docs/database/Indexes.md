# Indexes

Every index in this plugin's schema is declared in `BizHub\Workflow\Install\Schema::statements()` and applied via `dbDelta()`. There are no indexes added anywhere else (no runtime `ALTER TABLE`, no index management outside this class).

## `bizhub_workflow_instances`

| Index | Columns | Purpose |
|---|---|---|
| `PRIMARY KEY` | `id` | Standard auto-increment clustered key |
| `UNIQUE KEY uuid` | `uuid` | Enforces one row per instance UUID; backs every `find(uuid)` lookup and `exists()` check `WorkflowRepository::save()` uses to decide insert vs. update |
| `KEY workflow_type` | `workflow_type` | Backs `summaries(string $workflowType, ...)` — the admin screen's and any future list view's primary query pattern: "every instance of this workflow type" |
| `KEY subject` | `subject_type, subject_uuid` | Composite index backing `findForSubject()` — "every workflow ever run against this business subject," used whenever a business module needs a subject's full workflow history across possibly-multiple workflow types |
| `KEY status` | `status` | Supports filtering/reporting by current status (e.g. "how many Company Registrations are stuck in `awaiting_payment`") |

## `bizhub_workflow_transitions`

| Index | Columns | Purpose |
|---|---|---|
| `PRIMARY KEY` | `id` | Standard auto-increment clustered key |
| `UNIQUE KEY uuid` | `uuid` | Enforces one row per transition UUID |
| `KEY workflow_uuid` | `workflow_uuid` | Backs `history(string $workflowUuid)` — every transition for one instance, the query run every time a workflow is `find()`-ed (to attach its history) or its REST `show` endpoint is called with history requested |

## Deliberately not indexed

`action`, `actor_id`, `reason`, `occurred_at`, `metadata`, and `context` have no dedicated index on either table. None of the current query patterns (`WorkflowRepository`'s six interface methods) filter or sort by these columns directly at scale — `occurred_at` ordering is applied only within an already-`workflow_uuid`-filtered result set, which the `workflow_uuid` index already makes efficient. If a future reporting need requires querying transitions across all instances by `action` or `occurred_at` (e.g. "every `cancel` action in the last 30 days, platform-wide"), that would be the point to add a purpose-built index — there is no such need implemented today.

## No composite index across both tables

Because there is no database-level foreign key (see `Relationships.md`), there is naturally no cross-table composite index either — `WorkflowRepository::find()` issues two separate, independently-indexed queries (one against each table) and assembles the result in PHP via `withHistory()`.
