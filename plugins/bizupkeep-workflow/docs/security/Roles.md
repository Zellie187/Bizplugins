# Roles

## Role → capability grants (`config/permissions.php`)

`BizHub\Workflow\Install\RoleGrant::install()`, run once at activation, reads this file and calls `WP_Role::add_cap()` for every listed pair, skipping any role that does not exist on the site at that moment:

| Role | Origin | `workflow.view` | `workflow.manage` | `workflow.transition` |
|---|---|---|---|---|
| `administrator` | Native WordPress role | ✓ | ✓ | ✓ |
| `bizhub_administrator` | BizHub-defined role | ✓ | ✓ | ✓ |
| `bizhub_manager` | BizHub-defined role | ✓ | ✓ | ✓ |
| `bizhub_staff` | BizHub-defined role | ✓ | — | ✓ |

`bizhub_staff` is the only role granted a strict subset — staff can view workflows and drive them forward with actions, but cannot start a new Company Registration or roll one back, both of which require `workflow.manage`.

## Why grants happen at activation, not at boot

`RoleGrant` runs from `Activator::activate()` (WordPress's `register_activation_hook`), independent of BizHub's DI container and boot lifecycle, for the same reason the database migration does: activation must work as a self-contained, synchronous callback regardless of whether BizHub has finished booting in the same request. If a site's roles change later (e.g. a new custom role introduced by another plugin), re-running activation (deactivate then reactivate) is currently the only way to (re-)apply these grants — there is no automatic re-sync on every `plugins_loaded`.

## Uninstall reverses this, opt-in only

`uninstall.php` removes all three capabilities from exactly these four roles (`administrator`, `bizhub_administrator`, `bizhub_manager`, `bizhub_staff`), but only runs at all if the site admin opted into `bizupkeep_workflow_delete_data_on_uninstall` — see `docs/deployment/Rollback.md`.

## No custom roles introduced

This plugin defines zero new WordPress roles of its own — it only grants its own capabilities onto roles that BizHub (or WordPress core, for `administrator`) already defines. If a site does not have BizHub's `bizhub_manager`/`bizhub_staff` roles active for any reason, `RoleGrant::install()` silently skips granting to them (via the `! $role instanceof WP_Role` guard) rather than erroring — though in practice this should not happen, since BizHub is a hard runtime dependency (`DependencyGuard`) and is what defines those roles in the first place.
