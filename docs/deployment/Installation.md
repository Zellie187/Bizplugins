# Installation

## Prerequisites

- WordPress 6.7+, PHP 8.2+ (per the plugin header in `bizupkeep-workflow.php`).
- **BizHub** active first — this plugin's `Requires Plugins: bizhub, bizupkeep-core` header declares the dependency, enforced at the WordPress UI level on 6.5+, and backed at runtime by `BizHub\Workflow\Bootstrap\DependencyGuard`.
- **BizUpKeep Core** active first — the same header/runtime mechanism applies. `DependencyGuard::coreActive()` checks for `defined('BIZUPKEEP_CORE_VERSION')`.

If either is missing when this plugin's `plugins_loaded` callback runs (priority 20), `DependencyGuard::checkAndNotify()` registers an admin notice naming exactly which dependency is missing and deactivates this plugin via `admin_init` — it will not run in a partially-integrated state.

## Building for production

This plugin ships composer dependencies (`php-di/php-di`) that must be present in the deployed zip; dev tooling (PHPUnit, PHPStan, PHPCS, WordPress stubs) must not be:

```
composer install --no-dev --optimize-autoloader
```

run from this plugin's root, before packaging `bizupkeep-workflow.php` and its supporting directories into a distributable zip. Do not ship `tests/`, `phpunit.xml`, `phpcs.xml`, or `phpstan.neon` in a production build (they carry no runtime dependency, but are not needed there either).

## Activation sequence

Once uploaded and activated (with both dependencies already active), WordPress's `register_activation_hook` callback in `bizupkeep-workflow.php` runs `Activator::activate()`, which in order:

1. Runs `Migrator::migrate()` — creates `bizhub_workflow_instances`/`bizhub_workflow_transitions` via `dbDelta()` (see `docs/database/Migrations.md`).
2. Runs `RoleGrant::install()` — grants `workflow.view`/`workflow.manage`/`workflow.transition` to `administrator`, `bizhub_administrator`, `bizhub_manager`, `bizhub_staff` per `config/permissions.php` (see `docs/security/Roles.md`).
3. Creates `storage/{cache,logs,sessions,workflow}` directories with `index.php` silence files.
4. Calls `flush_rewrite_rules()` so the new REST routes resolve correctly.

Note: `Constants::register()` runs unconditionally at file-inclusion time (not gated behind `DependencyGuard`), because `Activator`/`RoleGrant` need the `BIZUPKEEP_WORKFLOW_*` path constants during activation, which can occur before `plugins_loaded` has fired for this plugin in the same request. The activation callback itself, however, *is* gated: `register_activation_hook`'s callback checks `DependencyGuard::satisfied()` and returns early (skipping `Activator::activate()` entirely) if BizHub/BizUpKeep Core are not yet active.

## After activation

On the next `plugins_loaded` (priority 20), `Plugin::instance()->boot()` registers translations, REST routes (`routes/api.php`), and the admin menu submenu (`bizhub` → `Workflows`) — see `docs/architecture/Integration-Architecture.md` for the full boot sequence.
