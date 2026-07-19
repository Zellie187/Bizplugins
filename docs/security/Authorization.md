# Authorization

## The rule: always through `AuthorizationServiceInterface`, never through raw WordPress capability functions

Every authorization decision in this plugin's business logic goes through BizHub's shared `BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface::can(int $userId, string $capability): bool`. This plugin never calls `current_user_can()` or `user_can()` directly from a Controller or Service. The one deliberate exception is `WorkflowAdminMenu::register()`/`render()`, a plain WordPress admin-screen page, which checks the native `manage_options` capability directly via `current_user_can('manage_options')` — consistent with how ordinary WordPress admin screens (including BizHub's own) are gated, and distinct from this plugin's own custom capabilities.

```php
private function can(string $capability): bool
{
    return $this->authorizationService->can(get_current_user_id(), $capability);
}
```

`CompanyRegistrationController` calls this once per method, immediately, before doing any other work — `start()`/`rollback()` require `Capabilities::WORKFLOW_MANAGE`, `show()` requires `Capabilities::WORKFLOW_VIEW`, `performAction()` requires `Capabilities::WORKFLOW_TRANSITION`. A failed check returns a `403` `WP_Error` immediately, before any input validation or engine call.

## Registering capabilities with BizHub

Custom capability strings only become meaningful to WordPress once granted to a role (see `Roles.md`). Separately, `WorkflowServiceProvider::boot()` calls `AuthorizationServiceInterface::registerCapability()` once per capability this plugin introduces, so BizHub's authorization layer is aware every one of `Capabilities::all()` exists as a first-class capability in the platform, not just an ad-hoc string.

## Why this indirection matters

Routing every check through `AuthorizationServiceInterface` rather than WordPress's native functions means this plugin's authorization behaviour is governed entirely by BizHub's own authorization implementation — if BizHub later adds a caching layer, an audit hook, or a policy-override mechanism to `can()`, every authorization check in this plugin inherits it automatically, with zero code changes here. It also means this plugin's authorization logic is trivially mockable in tests: any test exercising a Controller can substitute a fake `AuthorizationServiceInterface` without touching WordPress's real role/capability tables.

## What is not implemented

There is no per-workflow-instance (row-level) authorization beyond the type-level capability check — e.g. there is no rule limiting a `bizhub_staff` user to only the workflow instances for companies they personally manage. Authorization in this plugin is capability-level only. See `Permissions.md` and `Roles.md` for the concrete capability/role mapping, and `docs/architecture/Permissions-Architecture.md` for the architectural view of the same mechanism.
