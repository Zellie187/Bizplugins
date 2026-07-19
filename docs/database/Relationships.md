# Table Relationships

## `bizhub_workflow_transitions.workflow_uuid` → `bizhub_workflow_instances.uuid`

This is the only relationship in this plugin's schema: every transition row belongs to exactly one workflow instance, referenced by UUID rather than by the instance's auto-increment `id`. It is a logical, application-enforced relationship — there is **no** `FOREIGN KEY` constraint declared in `Schema.php`. This follows the same convention BizHub's own first-party modules use, and keeps `dbDelta()` migrations simpler (MySQL foreign keys interact poorly with `dbDelta`'s diff-based `ALTER TABLE` generation). Referential integrity is instead guaranteed by construction: `WorkflowRepository::recordTransition()` is only ever called by `WorkflowManager` immediately after `save()`-ing the owning instance, using that same instance's UUID.

## Workflow instance → business subject (outside this schema)

A workflow instance is bound to a business subject via the pair `subject_type` + `subject_uuid` (e.g. `subject_type = 'company'`, `subject_uuid` = a `BizHub\Companies` company's UUID). This is **not** a foreign key into any BizHub table either — the workflow engine deliberately knows nothing about what a "company" is; it only stores an opaque type/UUID pair. `WorkflowRepositoryInterface::findForSubject(string $subjectType, string $subjectUuid)` is how the engine answers "every workflow ever run against this subject" without needing to know anything about the subject's own schema.

## UUIDs, not auto-increment IDs, are the public/cross-reference identifier

Every reference between the engine's own concepts (instance ↔ transition) and between the engine and the outside world (instance ↔ subject, instance ↔ REST API caller) uses the `uuid CHAR(36)` column, never the internal `id BIGINT` primary key. The `id` columns exist purely as MySQL primary keys for storage efficiency; no code in this plugin (repository, service, controller, or admin screen) ever reads or exposes an `id` value.

## One instance, many transitions, one-directional history

The relationship is 1-to-many, one instance to many transitions, and strictly append-only in one direction: `WorkflowRepository::history()` always returns transitions ordered `occurred_at ASC` (oldest first), and nothing in this codebase ever updates or deletes a transition row post-insert. A rollback does not remove or edit the transition it is rolling back — it appends a new transition (action `rollback`) recording the reversal, so the full sequence of events, including any rollback, remains reconstructable from the transitions table alone.

## No relationship to notifications or roles

`config/notifications.php` and `config/permissions.php` are plain PHP config, not database tables — there is no `notifications` or `permissions` table in this schema. See `docs/database/Migrations.md` for what `dbDelta()` actually creates, and `Tables.md` for the full column list.
