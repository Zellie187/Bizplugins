# Acceptance Tests

**Status: not implemented.** `tests/Feature/` exists (containing only a `.gitkeep` placeholder) but is currently empty — no acceptance/feature test exercises this plugin's REST Controllers, WordPress capability wiring, or admin screen against a real, running WordPress instance. This gap is honest: no WordPress test environment (a real `wp-env`, a Local by Flywheel site, or an equivalent staging WordPress install with BizHub and BizUpKeep Core actually active) was available in the environment this codebase was developed and verified in. Every one of the 32 passing tests described in `Unit-Tests.md`/`Integration-Tests.md`/`Workflow-Tests.md` runs against plain PHPUnit with the minimal function stubs in `tests/bootstrap.php` — none of them boot real WordPress.

## What is not covered as a result

- `CompanyRegistrationController`'s four REST methods have never been exercised via an actual HTTP request against a real `rest_api_init`-registered route.
- `permission_callback`'s `is_user_logged_in()` and `AuthorizationServiceInterface::can()`'s real capability-checking behaviour have never been exercised against real WordPress user/role/capability tables (`RoleGrant`'s `WP_Role::add_cap()` calls are untested against a real `wp_options`/`wp_usermeta` schema).
- `WorkflowAdminMenu`'s rendered HTML has never been rendered inside a real `wp-admin` page load.
- The full activation flow (`Activator::activate()` → `Migrator::migrate()` → real `dbDelta()` against real MySQL → `RoleGrant::install()` against real roles) has never run end-to-end.
- `DependencyGuard`'s deactivation-with-admin-notice behaviour has never been exercised against a real plugin-activation lifecycle.

## What should be added, and how

A `wp-env` (or Local by Flywheel / equivalent) instance with BizHub, BizUpKeep Core, and this plugin all actually installed and activated, then:

1. **REST acceptance tests** — real HTTP requests (e.g. via `wp-env run cli wp rest ...` or a PHPUnit `WP_UnitTestCase`/`WP_REST_Controller`-style test base against a live install) against all four endpoints, verifying real cookie/nonce authentication, real capability enforcement (creating test users in each of the four roles from `Roles.md` and confirming access is granted/denied correctly), and real HTTP status codes matching `docs/api/Examples.md`.
2. **Activation acceptance tests** — activating the plugin fresh, asserting the two real tables exist with the documented schema (`docs/database/Tables.md`), the four capabilities are granted to the documented roles, and re-activation is idempotent.
3. **Admin screen acceptance tests** — a browser-level (e.g. Playwright/Puppeteer against the `wp-env` instance) check that the admin screen renders, is reachable only by `manage_options` users, and reflects real data.

Until this exists, treat this plugin's REST/WordPress-integration behaviour as *believed correct by code review and unit/integration testing of its underlying components*, not as independently verified end-to-end.
