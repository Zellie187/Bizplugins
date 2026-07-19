# Migrations

## The mechanism: `dbDelta()`, orchestrated by `Migrator`

`BizHub\Workflow\Install\Migrator` is the entire migration system for this plugin — there is no separate migration-file-per-version mechanism, no `migrations/` directory of ordered SQL files, and no third-party migration library. Instead, `Migrator::migrate()` re-applies the *current, complete* schema every time it runs, via WordPress's own `dbDelta()`:

```php
public function migrate(): void
{
    if (! \function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charsetCollate = $this->wpdb->get_charset_collate();

    foreach ($this->schema->statements($this->wpdb->prefix, $charsetCollate) as $sql) {
        dbDelta($sql);
    }

    update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
}
```

`dbDelta()` is idempotent and diff-based: given a full `CREATE TABLE` statement, it compares it against the live table structure and issues only the `ALTER TABLE` statements needed to reconcile the two — creating the table if it does not exist, adding/modifying columns and indexes if it does, and doing nothing if the table already matches. This means the same `Schema::statements()` output is safe to run on every activation *and* every version upgrade — there is nothing to "roll forward" incrementally.

## Versioning

`Migrator::CURRENT_VERSION = '1.0.0'` is a plain class constant, recorded in the `bizupkeep_workflow_db_version` WordPress option after every successful `migrate()` call. `installedVersion()` reads that option back; `needsMigration()` compares it against `CURRENT_VERSION`. There is no automatic upgrade-detection hook wired to `plugins_loaded` today — `migrate()` is currently only invoked from `Activator::activate()` (registered on WordPress's `register_activation_hook`), meaning schema changes are applied when the plugin is activated (including re-activation after an update, if the site admin deactivates/reactivates), not automatically mid-request on every version bump. See `docs/deployment/Upgrades.md` for the operational implications of this.

## When to bump `CURRENT_VERSION`

Any change to `Schema::statements()`'s `CREATE TABLE` bodies (a new column, a changed type, a new index) must be paired with bumping `Migrator::CURRENT_VERSION`, per the comment directly above the constant in source. `dbDelta()` will pick up and apply the structural change regardless of whether the version constant changes, but the version option is this plugin's own record of "what schema shape do we believe is installed," and letting it drift out of sync with the real schema would make `needsMigration()` unreliable.

## Activation flow

`Activator::activate()` runs three things, in order: `(new Migrator($wpdb, new Schema()))->migrate()`, then `(new RoleGrant())->install()`, then `createStorageDirectories()` (creating `storage/{cache,logs,sessions,workflow}` with an `index.php` silence file in each), followed by `flush_rewrite_rules()`. This is deliberately *not* resolved through BizHub's DI container — activation must work as a standalone, synchronous WordPress hook callback, independent of whether BizHub has finished booting in the same request (see `docs/architecture/Integration-Architecture.md`).
