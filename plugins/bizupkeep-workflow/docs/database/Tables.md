# Database Tables

BizUpKeep Workflow owns exactly two tables, both defined in `BizHub\Workflow\Install\Schema::statements()` and applied via `BizHub\Workflow\Install\Migrator` (`dbDelta()`). Table names are unprefixed in the schema source and prefixed at runtime with WordPress's own `$wpdb->prefix`.

## `{$prefix}bizhub_workflow_instances`

One row per workflow instance — its current status and metadata snapshot (not its history; see the transitions table below).

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | no | Primary key |
| `uuid` | `CHAR(36)` | no | Unique. The public identifier used by the engine, the API, and the admin screen |
| `workflow_type` | `VARCHAR(100)` | no | e.g. `company_registration` |
| `subject_type` | `VARCHAR(100)` | no | e.g. `company` |
| `subject_uuid` | `CHAR(36)` | no | The business entity this workflow is bound to |
| `status` | `VARCHAR(32)` | no | A `WorkflowStatus` enum value, e.g. `documents_verified` |
| `metadata` | `LONGTEXT` | yes | JSON-encoded `array<string,mixed>` |
| `created_by` | `BIGINT UNSIGNED` | no | WordPress user ID |
| `created_at` | `DATETIME` | no | |
| `updated_at` | `DATETIME` | yes | Null until the first transition |
| `completed_at` | `DATETIME` | yes | Set once, the first time the instance reaches a successful status |

Keys: `PRIMARY KEY (id)`, `UNIQUE KEY uuid (uuid)`, `KEY workflow_type (workflow_type)`, `KEY subject (subject_type, subject_uuid)`, `KEY status (status)`.

## `{$prefix}bizhub_workflow_transitions`

One row per state change ever applied to an instance — the append-only audit trail.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | no | Primary key |
| `uuid` | `CHAR(36)` | no | Unique. The transition's own identifier |
| `workflow_uuid` | `CHAR(36)` | no | Foreign reference to `bizhub_workflow_instances.uuid` (not a DB-level foreign key — see `Relationships.md`) |
| `from_status` | `VARCHAR(32)` | yes | Null for the very first transition an instance ever receives |
| `to_status` | `VARCHAR(32)` | no | |
| `action` | `VARCHAR(100)` | no | e.g. `verify_documents`, or the synthetic `rollback` |
| `actor_id` | `BIGINT UNSIGNED` | no | WordPress user ID who performed the action |
| `reason` | `VARCHAR(500)` | no | Defaults to `''`; free text |
| `context` | `LONGTEXT` | yes | JSON-encoded `array<string,mixed>`, the action-specific data supplied by the caller |
| `occurred_at` | `DATETIME` | no | |

Keys: `PRIMARY KEY (id)`, `UNIQUE KEY uuid (uuid)`, `KEY workflow_uuid (workflow_uuid)`.

Both tables' exact `CREATE TABLE` statements live in `includes/Install/Schema.php` — that file, not this document, is the source of truth if the two ever disagree.
