# API Architecture

BizUpKeep Workflow exposes a single REST resource today — Company Registration workflows — under the namespace `bizupkeep-workflow/v1`, registered via `routes/api.php` on WordPress's `rest_api_init` hook (wired up by `BizHub\Workflow\Bootstrap\Plugin::registerRoutes()`).

## Route registration is container-driven

`routes/api.php` resolves its controller from BizHub's shared container rather than constructing it directly:

```php
$application = bizhub();
if ($application === null) {
    return;
}
$container = $application->container();
$controller = $container->get(CompanyRegistrationController::class);
```

If `bizhub()` returns `null` (BizHub has not booted — which should not happen once `DependencyGuard` has passed, but is guarded against defensively), no routes are registered at all rather than risking a fatal error. Because the controller is resolved from the container, every one of its dependencies (`CompanyRegistrationService`, `AuthorizationServiceInterface`, `Logger`) is wired exactly as it would be anywhere else in BizHub, with no manual `new` calls.

## Layering: thin controllers

`CompanyRegistrationController` is deliberately thin (BH-WORKFLOW-SPEC-001 section 4): each method (1) checks authorization via `AuthorizationServiceInterface::can()`, (2) builds and validates a typed input object via an `Http\Requests\*` class, (3) delegates entirely to `CompanyRegistrationService`, and (4) maps the result or a caught exception to a `WP_REST_Response`/`WP_Error`. It never calls the workflow engine, a repository, or the database directly.

## Response shaping

`CompanyRegistrationController::present()` is the single place response payloads are built, so every endpoint returns a consistent shape: `uuid`, `workflow_type`, `subject_type`, `subject_uuid`, `status`, `status_label`, `metadata`, `created_at`/`updated_at`/`completed_at` (ISO 8601 via `DATE_ATOM`), and — only for the `show` endpoint — a `history` array of every recorded transition.

## Exception-to-status mapping

`handle()` maps a fixed set of known exceptions to HTTP statuses (422 validation, 404 not found, 409 conflict) and everything else to a logged, generic 500. See `Security-Architecture.md` and `docs/api/Examples.md`.

## Versioning

The namespace itself (`bizupkeep-workflow/v1`) is this plugin's versioning strategy: a breaking change to the API contract would ship as `bizupkeep-workflow/v2` registered alongside (or instead of) `v1`, following WordPress REST API convention. There is no other API versioning scheme in place.

See `docs/api/REST.md`, `docs/api/Endpoints.md`, and `docs/api/Authentication.md` for the full endpoint reference and auth model.
