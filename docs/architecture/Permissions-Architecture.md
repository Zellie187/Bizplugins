# Permissions Architecture

## Three capabilities

`BizHub\Workflow\Policies\Capabilities` defines every capability this plugin introduces:

```php
public const WORKFLOW_VIEW = 'workflow.view';
public const WORKFLOW_MANAGE = 'workflow.manage';
public const WORKFLOW_TRANSITION = 'workflow.transition';
```

`Capabilities::all()` returns all three. Code should always reference these constants rather than hardcoding the capability strings.

## How capabilities become real WordPress capabilities

Custom capability strings like `workflow.manage` are not native WordPress capabilities — WordPress's `user_can()` only understands a capability once some role has been granted it. Two things make that happen for this plugin:

1. **`BizHub\Workflow\Providers\WorkflowServiceProvider::boot()`** calls `AuthorizationServiceInterface::registerCapability()` once per capability, telling BizHub's authorization layer that these capabilities exist.
2. **`BizHub\Workflow\Install\RoleGrant::install()`**, run once at activation by `Activator`, reads `config/permissions.php` and calls WordPress's `WP_Role::add_cap()` for every configured role/capability pair, for any role that actually exists at that moment.

## Role grants (`config/permissions.php`)

| Role | `workflow.view` | `workflow.manage` | `workflow.transition` |
|---|---|---|---|
| `administrator` (native WP role) | yes | yes | yes |
| `bizhub_administrator` | yes | yes | yes |
| `bizhub_manager` | yes | yes | yes |
| `bizhub_staff` | yes | no | yes |

Only `bizhub_staff` is deliberately narrower: staff can view and drive workflows forward through transitions, but cannot perform manager-level operations (starting a new workflow, or rolling one back) mapped to `workflow.manage`.

## Enforcement pattern

All authorization checks in this plugin's business logic go through `AuthorizationServiceInterface::can(int $userId, string $capability): bool` — never through WordPress's `current_user_can()` directly (the sole exception being the plain WordPress admin screen in `WorkflowAdminMenu`, gated by the native `manage_options` capability). `CompanyRegistrationController` demonstrates the mapping used across the API:

| Endpoint | Capability required |
|---|---|
| `POST /company-registrations` | `workflow.manage` |
| `GET /company-registrations/{uuid}` | `workflow.view` |
| `POST /company-registrations/{uuid}/actions` | `workflow.transition` |
| `POST /company-registrations/{uuid}/rollback` | `workflow.manage` |

## Uninstall

`uninstall.php` reverses `RoleGrant`'s work when the user has opted in to full data deletion: it removes all three capabilities from `administrator`, `bizhub_administrator`, `bizhub_manager`, and `bizhub_staff`.

See `docs/security/Roles.md` and `docs/security/Permissions.md` for the security-focused write-up of the same mechanism, and `docs/security/Authorization.md` for the `AuthorizationServiceInterface::can()` contract itself.
