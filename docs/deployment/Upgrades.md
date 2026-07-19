# Upgrades

## Schema upgrades: `Migrator::CURRENT_VERSION` + idempotent `dbDelta()`

`BizHub\Workflow\Install\Migrator::CURRENT_VERSION` (currently `'1.0.0'`) is the version identifier this plugin's schema is versioned against, recorded in the `bizupkeep_workflow_db_version` WordPress option. `Migrator::migrate()` does not run incremental, ordered migration scripts — it re-applies the plugin's *entire current* schema (`Schema::statements()`) via `dbDelta()` every time it runs, and `dbDelta()` itself is idempotent: it diffs the given `CREATE TABLE` statement against the live table and only issues the `ALTER TABLE`s needed to reconcile any difference, doing nothing if the table already matches. This means the exact same code path used at fresh activation is safe to re-run after a plugin file upgrade — there is no separate "upgrade path" distinct from "fresh install path."

```php
public function migrate(): void
{
    // ...
    foreach ($this->schema->statements($this->wpdb->prefix, $charsetCollate) as $sql) {
        dbDelta($sql);
    }
    update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
}
```

## When migration actually runs

`migrate()` is currently invoked only from `Activator::activate()`, itself only invoked by WordPress's `register_activation_hook`. This means: **a plugin file upgrade alone (replacing the plugin's files without deactivating/reactivating) does not automatically re-run the migration.** If a future release changes `Schema::statements()`, the practical upgrade path today is: deploy the new plugin files, then deactivate and reactivate the plugin (or otherwise explicitly invoke `Activator::activate()`, e.g. via WP-CLI), so `dbDelta()` reconciles the schema. There is no `plugins_loaded`-hooked auto-upgrade check (e.g. comparing `Migrator::needsMigration()` on every page load) wired up in this version.

## Bumping the schema version

Any change to `Schema::statements()`'s column/index definitions must be paired with incrementing `Migrator::CURRENT_VERSION`, per the doc comment directly above the constant. Failing to bump it does not break `dbDelta()` (which does not consult the version constant at all — it only compares live schema against the given `CREATE TABLE` text) but would leave `installedVersion()`/`needsMigration()` reporting a stale, misleading value.

## Plugin-code upgrades in general

Beyond the schema, there is no other explicit "upgrade routine" in this plugin — no data backfill scripts, no deprecated-option cleanup, no versioned migration of `metadata`/`context` JSON shapes. A future release changing the *shape* of stored JSON (e.g. renaming a context key a guard expects) would need to either remain backward-compatible with previously-stored rows or ship an explicit, additional one-off migration step — no such tooling exists today. See `docs/database/Versioning.md` for the schema-versioning model this builds on, and `Rollback.md` for what happens if an upgrade needs to be reversed.
