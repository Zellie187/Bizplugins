# Rollback

This term means two entirely different things in this codebase's context, and they must not be conflated.

## 1. Workflow-instance rollback (implemented, real feature)

`WorkflowEngineInterface::rollback(RollbackWorkflowCommand $command)`, implemented in `WorkflowManager::rollback()`, is a genuine, tested engine operation: it moves a single workflow instance back to the status it held immediately before its most recent transition, refusing to do so if the instance is already terminal or has no prior transition to return to. It is exposed over REST as `POST /company-registrations/{uuid}/rollback` (requires `workflow.manage`, requires a `reason`), and is fully documented in `docs/architecture/Workflow-Architecture.md`, `docs/workflows/Company-Registration.md`, and exercised by `tests/Workflow/CompanyRegistrationWorkflowTest.php`. This is a **business-process** rollback — reverting one *workflow instance's* state, not the plugin's code or database schema.

Rollback does not delete or edit the transition being reversed; it appends a new transition (`action = 'rollback'`) recording the reversal, so the full history — including the rollback itself — remains intact and auditable (see `docs/security/Audit.md`).

## 2. Plugin-version rollback (not specially supported — standard WordPress practice only)

Reverting the *plugin itself* to a previous release (e.g. because a new version introduced a regression) has **no special tooling built into this plugin**. It works the same way reverting any WordPress plugin does: replace the currently-installed plugin files with a previous release's zip, and reactivate if needed. Two things to be aware of when doing this:

- **Schema is not automatically reverted.** `Migrator`/`dbDelta()` only ever move a schema *forward* (reconciling toward whatever `Schema::statements()` the *currently installed* code declares) — see `docs/database/Versioning.md`, "No down-migrations." Rolling back plugin code to an older version whose `Schema::statements()` expects a narrower schema than what a newer version's migration already applied does not un-apply those changes; a genuinely safe plugin-version rollback that also needs schema reverted requires restoring a database backup taken before the newer version's migration ran.
- **Data is preserved by default.** Deactivating (or even deleting) the plugin does not touch `bizhub_workflow_instances`/`bizhub_workflow_transitions` unless the site admin has explicitly opted into `bizupkeep_workflow_delete_data_on_uninstall` (checked only in `uninstall.php`, which only runs on deletion via wp-admin, never on plain deactivation) — see `Deactivator::deactivate()`, which only calls `flush_rewrite_rules()`.

## Practical guidance

Before rolling back the plugin version, take a database backup. Reverted plugin code will happily operate against a schema shape from a *newer* version than itself, since `dbDelta()`/`Migrator` never remove columns — but do not assume this is safe in the general case without testing against your specific version delta.
