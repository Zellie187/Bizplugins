# API Authentication

## Cookie + nonce authentication, for logged-in users only

Every route registered in `routes/api.php` uses the identical `permission_callback`:

```php
'permission_callback' => static fn (): bool => is_user_logged_in(),
```

This is WordPress's standard, built-in REST authentication model for browser-based (same-origin, cookie-authenticated) clients: a request must carry a valid logged-in user's session cookies plus a valid `X-WP-Nonce` header (WordPress's REST server enforces the nonce check itself as part of cookie authentication, ahead of any plugin's `permission_callback` running). There is no API-key header, no OAuth flow, and no custom bearer-token scheme implemented anywhere in this plugin.

Application Passwords (WordPress core's built-in alternative for non-browser/headless API clients) are not explicitly configured or documented by this plugin, but nothing in `is_user_logged_in()` excludes them — any authentication method WordPress core itself recognises as establishing a logged-in user is sufficient to pass this check.

## Authentication is not authorization

`is_user_logged_in()` only answers "is there *some* authenticated user attached to this request" — it deliberately does not check *which* capabilities that user has. That is checked separately and explicitly, inside each controller method, via BizHub's shared authorization service:

```php
private function can(string $capability): bool
{
    return $this->authorizationService->can(get_current_user_id(), $capability);
}
```

`BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface::can()` is called once per controller method with the specific capability that operation requires (see `docs/security/Authorization.md` and `docs/architecture/Permissions-Architecture.md` for the `workflow.view`/`workflow.manage`/`workflow.transition` mapping). A capability failure returns a `403 Forbidden` `WP_Error` from the controller method itself, distinct from the `permission_callback`'s own (also 403-equivalent, WordPress-generated) rejection when there is no logged-in user at all.

## Why capability checks live in the controller, not the `permission_callback`

Keeping `permission_callback` as a simple "is anyone logged in" check, and moving the specific-capability decision into the method body via `AuthorizationServiceInterface`, means every capability check in this plugin goes through the exact same authorization abstraction the rest of the platform uses — there is no second, REST-specific authorization code path to keep in sync with BizHub's own `AuthorizationServiceInterface` implementation.

## Not yet implemented

There is no support for unauthenticated (public/anonymous) access to any endpoint, no per-client API keys, and no rate limiting beyond whatever WordPress/hosting infrastructure provides generally.
