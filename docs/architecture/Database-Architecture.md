# Database Architecture

BizUpKeep Workflow never touches `$wpdb` (or any other database driver) directly. Every persistence operation goes through BizHub's own `BizHub\Framework\Database\Contracts\DatabaseInterface`, injected into `BizHub\Workflow\Repositories\WorkflowRepository`, which is the *only* class in this plugin that talks to the database.

## Two tables, one repository

The engine owns exactly two tables, defined in `BizHub\Workflow\Install\Schema`:

- **`{$prefix}bizhub_workflow_instances`** — one row per workflow instance: its current status, metadata, and lifecycle timestamps.
- **`{$prefix}bizhub_workflow_transitions`** — one row per state change ever applied to an instance: the durable, append-only audit trail.

Both are documented column-by-column in `docs/database/Tables.md`. They live in this plugin (not in BizHub Framework itself) because the workflow engine is this plugin's own bounded context — the same convention BizHub uses for its own first-party modules (Companies, Documents, etc.).

`WorkflowRepository implements WorkflowRepositoryInterface` and provides: `find()`, `findForSubject()` (every workflow ever run against one business subject), `summaries()` (lightweight `WorkflowSummary` projections for list views, ordered most-recently-updated first), `save()` (insert-or-update on the instances table, keyed by UUID existence check), `recordTransition()` (append-only insert on the transitions table), and `history()` (the full ordered transition list for one instance).

## Hydration

`WorkflowRepository::hydrate()`/`dehydrate()` convert between database rows and `WorkflowInstance` objects. `status` is stored as the enum's string value and parsed back via `WorkflowStatus::from()`; `metadata` and `context` are stored as `LONGTEXT` JSON (`json_encode(..., JSON_UNESCAPED_SLASHES)` / `json_decode(..., true)`); timestamps are stored as `DATETIME` and parsed back into `DateTimeImmutable`.

## Migrations

Schema changes are applied through WordPress's own `dbDelta()`, orchestrated by `BizHub\Workflow\Install\Migrator` — see `docs/database/Migrations.md` for the full activation/upgrade flow, and `docs/deployment/Upgrades.md` for the versioning contract.

## Why the repository pattern matters here

Routing every read/write through `WorkflowRepositoryInterface` means the engine's core (`WorkflowManager`) has zero knowledge of SQL, table names, or JSON encoding — it only ever manipulates `WorkflowInstance` and `Transition` objects. This is also what makes the engine testable without a real MySQL server: `tests/Integration/Repositories/WorkflowRepositoryTest.php` exercises the real `WorkflowRepository` class against `tests/Mocks/InMemoryDatabase.php`, a fake `DatabaseInterface` implementation (see `docs/testing/Integration-Tests.md`).

See `ERD.md` for the entity-relationship diagram and `Relationships.md`/`Indexes.md` for how the two tables relate and are keyed.
