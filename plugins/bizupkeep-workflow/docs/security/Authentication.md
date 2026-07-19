# Authentication (Security)

This plugin introduces no authentication mechanism of its own. It relies entirely on WordPress's built-in session/cookie authentication, mediated by BizHub for anything platform-wide.

## What is actually checked

Every REST route's `permission_callback` in `routes/api.php` is `static fn (): bool => is_user_logged_in()` — a check that a valid, currently-authenticated WordPress user is attached to the request (via cookie + nonce, WordPress core's standard REST authentication path). There is no plugin-specific login form, session store, password handling, or token issuance anywhere in this codebase. The one WordPress-facing UI screen this plugin ships (`WorkflowAdminMenu`) is likewise gated by WordPress's own admin authentication, not a custom mechanism.

## What this plugin deliberately does not do

- It does not verify passwords, issue or validate JWTs/API keys, or implement its own session handling.
- It does not weaken or bypass WordPress's authentication in any way — e.g. there is no "trusted internal request" bypass anywhere in `routes/api.php` or the Controller layer.
- It does not distinguish authentication methods (cookie session vs. Application Passwords vs. any other WordPress-recognised login) — `is_user_logged_in()` treats any of them identically.

## Division of responsibility

Authentication (who is this request from) is entirely out of scope for this plugin, by design — it is WordPress core's and, where BizHub extends it, BizHub's responsibility. This plugin's only responsibility, once a request is confirmed authenticated, is **authorization**: deciding what that already-identified user is allowed to do, via `AuthorizationServiceInterface::can()` (see `Authorization.md`). Keeping this boundary crisp is deliberate — a security review of authentication mechanics belongs to BizHub/WordPress core, not to this plugin's codebase.

See `docs/api/Authentication.md` for the REST-API-specific version of this same explanation, and `Authorization.md` for what happens after a request is confirmed authenticated.
