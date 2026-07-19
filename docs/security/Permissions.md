# Permissions

## The three capabilities

`BizHub\Workflow\Policies\Capabilities` is the single source of truth for every permission string this plugin introduces:

```php
final class Capabilities
{
    public const WORKFLOW_VIEW = 'workflow.view';
    public const WORKFLOW_MANAGE = 'workflow.manage';
    public const WORKFLOW_TRANSITION = 'workflow.transition';

    public static function all(): array { /* ... */ }
}
```

| Capability | Grants | Enforced on |
|---|---|---|
| `workflow.view` | Reading a workflow instance's current state and history | `GET /company-registrations/{uuid}` |
| `workflow.manage` | Starting a new workflow instance; rolling one back | `POST /company-registrations`, `POST /company-registrations/{uuid}/rollback` |
| `workflow.transition` | Performing a named action against an existing instance | `POST /company-registrations/{uuid}/actions` |

There is no capability finer-grained than this today — e.g. no separate capability distinguishing "can cancel" from "can approve"; any user with `workflow.transition` can perform any of Company Registration's nine actions (subject to the state machine and guard still enforcing which actions are structurally/business-rule valid at that moment).

## Enforcement, end to end

1. **Declaration** — `Capabilities::all()` lists every capability.
2. **Registration** — `WorkflowServiceProvider::boot()` calls `AuthorizationServiceInterface::registerCapability()` for each, once per request BizHub boots.
3. **Grant** — `RoleGrant::install()`, at activation, adds each capability to the roles configured in `config/permissions.php` (see `Roles.md`).
4. **Check** — `CompanyRegistrationController` calls `AuthorizationServiceInterface::can($userId, $capability)` at the top of every method.
5. **Revocation** — `uninstall.php` removes the grants, opt-in only, on full uninstall.

## Extending permissions for a new workflow type

New workflow types built on this engine reuse these same three capabilities rather than minting new ones per workflow type — `workflow.view`/`workflow.manage`/`workflow.transition` are intentionally generic across the whole engine, not scoped to Company Registration specifically. A future workflow type needing genuinely different access rules (e.g. a workflow only certain staff should even see exists) would need new, additional capability constants and a corresponding `config/permissions.php` update — no such need has arisen yet.

See `docs/architecture/Permissions-Architecture.md` for the architectural framing of this same mechanism, and `Roles.md`/`Authorization.md` for the role-grant and enforcement mechanics respectively.
