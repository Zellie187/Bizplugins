# Endpoint Reference

All endpoints are under the `bizupkeep-workflow/v1` namespace. Authentication is described in `Authentication.md`; response shapes and error mapping are detailed in `Examples.md`.

## `POST /company-registrations`

Starts a new Company Registration workflow for an existing company.

- **Capability required**: `workflow.manage`
- **Controller**: `CompanyRegistrationController::start()`
- **Request args** (validated by `StartCompanyRegistrationRequest`): `company_uuid` (string, required, must be a well-formed UUID)
- **Success**: `201 Created`, body = the workflow's `present()` shape (no `history`)
- **Failure modes**: `422` if `company_uuid` missing/malformed; `404` if the company does not exist (`CompanyNotFoundException`)

## `GET /company-registrations/{uuid}`

Retrieves a single Company Registration workflow, including its full transition history.

- **Capability required**: `workflow.view`
- **Controller**: `CompanyRegistrationController::show()`
- **Path params**: `uuid` (matched by `[a-f0-9\-]{36}`)
- **Success**: `200 OK`, body = `present()` shape **with** `history`
- **Failure modes**: `404` if no workflow exists with that UUID, or it exists but is not a Company Registration (`WorkflowNotFoundException`)

## `POST /company-registrations/{uuid}/actions`

Performs a named action against an existing workflow instance.

- **Capability required**: `workflow.transition`
- **Controller**: `CompanyRegistrationController::performAction()`
- **Path params**: `uuid`
- **Request args** (validated by `CompanyRegistrationActionRequest`): `action` (string, required — one of the nine action names in `Company-Registration.md`), `reason` (string, optional, trimmed), `context` (object, optional — action-specific data, e.g. `documents_verified`, `payment_reference`, `reviewed_by`)
- **Success**: `200 OK`, body = `present()` shape (no `history`)
- **Failure modes**: `422` if `action` missing or not a recognised Company Registration action; `404` if the workflow does not exist/is the wrong type; `409` if the action is structurally invalid from the current status (`InvalidTransitionException`) or its precondition is unmet (`PreconditionFailedException`)

## `POST /company-registrations/{uuid}/rollback`

Rolls a workflow instance back to the status it was in immediately before its most recent transition.

- **Capability required**: `workflow.manage`
- **Controller**: `CompanyRegistrationController::rollback()`
- **Path params**: `uuid`
- **Request args**: `reason` (string, **required**)
- **Success**: `200 OK`, body = `present()` shape (no `history`)
- **Failure modes**: `404` if not found/wrong type; `409` if the workflow is already terminal, or has no prior transition to return to (`InvalidTransitionException`)

## Common response fields (`present()`)

`uuid`, `workflow_type`, `subject_type`, `subject_uuid`, `status`, `status_label`, `metadata`, `created_at`, `updated_at`, `completed_at` — all timestamps formatted as ISO 8601 (`DATE_ATOM`). `show()` additionally includes `history`: an array of `{uuid, from_status, to_status, action, actor_id, reason, occurred_at}` objects, oldest first.
