# Security Architecture

## Authentication

BizUpKeep Workflow introduces no authentication mechanism of its own. Every REST route registered in `routes/api.php` uses WordPress's standard cookie + nonce authentication for logged-in users: each route's `permission_callback` is `static fn (): bool => is_user_logged_in()`. There is no API-key, OAuth, or custom token layer in this plugin. See `docs/api/Authentication.md`.

## Authorization

Capability enforcement is deliberately **not** performed in the REST `permission_callback` (which only gates "is there a logged-in user at all"). It happens explicitly inside each controller method, via BizHub's shared `BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface::can(int $userId, string $capability): bool`. `CompanyRegistrationController` calls this once per method with the specific capability that operation requires (`workflow.view`, `workflow.manage`, or `workflow.transition` — see `Permissions-Architecture.md`), returning a `403` `WP_Error` if the check fails. This plugin never calls WordPress's native `current_user_can()`/`user_can()` directly from business logic — the one exception is `WorkflowAdminMenu::render()`, which is a WordPress admin-screen page callback and checks the built-in `manage_options` capability directly, consistent with how BizHub's own admin screens are gated.

## Exception hygiene

`CompanyRegistrationController::handle()` only ever reflects a small, known set of purpose-built exceptions back to the API caller (`ValidationException` → 422, `WorkflowNotFoundException`/`CompanyNotFoundException` → 404, `PreconditionFailedException`/`InvalidTransitionException` → 409). Any other `Throwable` is logged in full server-side (via BizHub's `Logger`) and reported to the client only as a generic `500` "An unexpected error occurred" message — raw exception detail, stack traces, and internal class names are never exposed over the API. See BH-WORKFLOW-SPEC-001 section 11 and `docs/development/Exception-Standards.md`.

## Input validation

Every REST endpoint's input is shaped and validated by a dedicated `Http\Requests\*` class (`StartCompanyRegistrationRequest`, `CompanyRegistrationActionRequest`) before it reaches the Service layer. These classes only validate data *shape* (e.g. "is `company_uuid` a well-formed UUID"); business-rule validation (e.g. "does this company actually exist") is deliberately left to the Service layer, per BH-WORKFLOW-SPEC-001 section 9.

## No data of its own to encrypt

This module stores workflow *process state* (statuses, action names, free-text reasons, small JSON context blobs) — not secrets, passwords, or payment credentials. It defers entirely to BizHub's own encryption-at-rest and transport security for anything genuinely sensitive elsewhere in the platform. See `docs/security/Encryption.md`.

## Audit trail as a security control

Every transition is durably recorded in `bizhub_workflow_transitions` with an actor ID, a reason, and a timestamp, and is additionally written to BizHub's structured log. This gives the platform a tamper-evident (append-only, never updated in place) record of who changed what workflow state and when — see `docs/security/Audit.md`.
