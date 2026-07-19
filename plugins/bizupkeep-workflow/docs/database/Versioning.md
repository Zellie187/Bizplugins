# Schema Versioning

## The version record

`Migrator::CURRENT_VERSION` (currently `'1.0.0'`) is the schema's version identifier, and the `bizupkeep_workflow_db_version` WordPress option is where the currently-installed version is recorded — written by `Migrator::migrate()` after every successful `dbDelta()` run, and read back by `Migrator::installedVersion()`/`needsMigration()`.

This is a single scalar version, not a per-migration ledger — there is no table recording "which individual migrations have run," because there are no individual migrations to track (see `Migrations.md`): the entire schema is redeclared and reconciled by `dbDelta()` on every `migrate()` call, so "the installed version" and "the schema shape" are meant to always describe the same thing.

## Relationship to the plugin version

`Migrator::CURRENT_VERSION` (`1.0.0`) and the plugin's own version (`BIZUPKEEP_WORKFLOW_VERSION`, also currently `1.0.0`, declared in `bizupkeep-workflow.php`'s header and `define()`) happen to match at this release, but they are **independent** values tracking independent things: the plugin version tracks the overall codebase release; the schema version tracks only the database structure. A future plugin release that changes no table structure would ship a new plugin version without bumping `Migrator::CURRENT_VERSION`; a schema-only fix would bump the schema version without necessarily being a user-facing plugin release.

## When the schema version must change

Any structural change to `Schema::statements()` (new column, changed column type/length, new/changed index, new table) requires bumping `Migrator::CURRENT_VERSION`, per the doc comment directly above the constant. This is a manual, deliberate step — nothing in this codebase derives the version automatically from the schema's contents.

## No down-migrations

There is no mechanism to revert a schema change once `dbDelta()` has applied it (no "migrate down" equivalent). `dbDelta()` is additive/reconciling by nature — it will add a new column or index, but will not drop a column that a newer `Schema::statements()` version removed, unless the removal is also expressed as an explicit `dbDelta()`-compatible statement change. Reverting a schema change in practice means restoring from a database backup taken before the change, or writing and running a one-off SQL script — this plugin provides no tooling for either. See `docs/deployment/Rollback.md` for how plugin-version rollback and this schema-versioning model interact.

## Uninstall is a separate, opt-in path

Dropping the tables entirely is handled by `uninstall.php`, not by `Migrator`, and only runs when the site admin has explicitly enabled `bizupkeep_workflow_delete_data_on_uninstall` — see `docs/deployment/Rollback.md` and `docs/security/Compliance.md` for why data is preserved by default across deactivation/reinstallation.
