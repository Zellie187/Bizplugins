# Configuration

Four plain PHP config files under `config/`, each returning a single array, read directly by the code that needs them (no config-caching layer, no environment-variable overlay) — none are WordPress options or database-stored; changing any of them requires a code deployment.

## `config/workflow.php`

A registry/reference of workflow types shipped with this plugin, currently listing only `company_registration` (`{'definition' => CompanyRegistrationDefinition::class, 'label' => 'Company Registration'}`). This is documentation-only — it does not itself register anything with the engine (that happens in each workflow type's own Service Provider, see `docs/architecture/Workflow-Architecture.md`). Read by nothing else in the codebase today; it exists purely as a discoverable reference for integrators.

## `config/events.php`

A reference registry mapping every event class this plugin can raise (`WorkflowCreated`, `WorkflowTransitioned`, `WorkflowCompleted`, `WorkflowCancelled`, `WorkflowRolledBack`) to a one-line description. Also documentation-only — listener registration happens in each event's owning Service Provider, not by reading this file. See `docs/development/Event-Conventions.md`.

## `config/permissions.php`

The one config file with real runtime effect at activation time: maps role name → array of capability constants, read by `RoleGrant::install()` (called from `Activator::activate()`) to grant this plugin's three capabilities to `administrator`, `bizhub_administrator`, `bizhub_manager` (all three capabilities), and `bizhub_staff` (`workflow.view`/`workflow.transition` only). See `docs/security/Roles.md`.

## `config/notifications.php`

Maps workflow type → action name → `{subject, body}` template pair, read by `WorkflowNotificationListener`'s constructor on every request (not cached) and consulted every time a `WorkflowTransitioned` event fires. Currently only `company_registration` has entries, covering seven of its nine actions. See `docs/architecture/Notification-Architecture.md`.

## Changing configuration

Editing any of these four files requires deploying a new plugin build — there is no admin-UI editor for any of them. `config/permissions.php` changes only take effect for *newly* activated roles/sites unless the plugin is reactivated (re-running `RoleGrant::install()`) or a site admin manually re-grants capabilities; `config/notifications.php` and `config/events.php` take effect immediately on the next request, since they are read fresh each time rather than cached.
