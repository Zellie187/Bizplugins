# REST API Overview

BizUpKeep Workflow registers a REST namespace, `bizupkeep-workflow/v1`, on WordPress's `rest_api_init` hook. Registration happens in `routes/api.php`, required by `BizHub\Workflow\Bootstrap\Plugin::registerRoutes()`.

## Currently implemented: Company Registration only

Four endpoints exist today, all for the Company Registration workflow, all handled by `BizHub\Workflow\Http\Controllers\CompanyRegistrationController`:

| Method | Path | Controller method |
|---|---|---|
| `POST` | `/wp-json/bizupkeep-workflow/v1/company-registrations` | `start()` |
| `GET` | `/wp-json/bizupkeep-workflow/v1/company-registrations/{uuid}` | `show()` |
| `POST` | `/wp-json/bizupkeep-workflow/v1/company-registrations/{uuid}/actions` | `performAction()` |
| `POST` | `/wp-json/bizupkeep-workflow/v1/company-registrations/{uuid}/rollback` | `rollback()` |

See `Endpoints.md` for full parameter/response detail and `Examples.md` for real request/response bodies.

## Controller resolution is container-driven

`routes/api.php` never constructs the controller itself:

```php
$application = bizhub();
if ($application === null) {
    return;
}
$controller = $application->container()->get(CompanyRegistrationController::class);
```

If BizHub has not booted (`bizhub()` returns `null`), no routes are registered — there is no fallback route registration path.

## `{uuid}` route parameter format

Every `{uuid}` path segment is matched by the regex `[a-f0-9\-]{36}`, matching the lowercase-hex, hyphenated UUID format `BizHub\Framework\Support\Uuid::generate()` produces. A malformed UUID in the URL simply fails to match the route at all (WordPress returns its own 404 for an unmatched route), rather than reaching the controller and failing there.

## Response envelope

Every successful response is a `WP_REST_Response` built from `CompanyRegistrationController::present()` — a flat JSON object (never wrapped in a `data` envelope) with `uuid`, `workflow_type`, `subject_type`, `subject_uuid`, `status`, `status_label`, `metadata`, `created_at`, `updated_at`, `completed_at`, and (only on `show()`) a `history` array. Errors are `WP_Error` objects, which WordPress's REST server serializes as `{"code": "...", "message": "...", "data": {"status": ...}}`. See `Examples.md`.

## Versioning

`v1` in the namespace is the only versioning mechanism. A breaking contract change would ship as a new namespace (`bizupkeep-workflow/v2`) rather than mutating `v1` in place, following standard WordPress REST API practice — there is no other versioning scheme implemented.

## Not yet implemented

There is no endpoint for any workflow type other than Company Registration (see `docs/workflows/` for the 15 specified-but-unbuilt types), no bulk/list endpoint (the only way to enumerate instances today is the admin screen calling `WorkflowRepositoryInterface::summaries()` directly, not via REST), and no webhook delivery (`Webhooks.md`).
