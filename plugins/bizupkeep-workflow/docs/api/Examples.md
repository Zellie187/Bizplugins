# API Examples

All examples assume WordPress cookie authentication (a valid `wordpress_logged_in_*` cookie plus a valid `X-WP-Nonce` header) for a user holding the required capability. See `Authentication.md`.

## Start a Company Registration

```
POST /wp-json/bizupkeep-workflow/v1/company-registrations
X-WP-Nonce: abc123
Content-Type: application/json

{
  "company_uuid": "8f14e45f-ceea-4c9b-8b3f-2f1a3d4e5f6a"
}
```

```
HTTP/1.1 201 Created
Content-Type: application/json

{
  "uuid": "b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab",
  "workflow_type": "company_registration",
  "subject_type": "company",
  "subject_uuid": "8f14e45f-ceea-4c9b-8b3f-2f1a3d4e5f6a",
  "status": "created",
  "status_label": "Created",
  "metadata": [],
  "created_at": "2026-07-19T09:15:00+00:00",
  "updated_at": null,
  "completed_at": null
}
```

## Retrieve a workflow, with history

```
GET /wp-json/bizupkeep-workflow/v1/company-registrations/b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab
```

```json
{
  "uuid": "b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab",
  "workflow_type": "company_registration",
  "subject_type": "company",
  "subject_uuid": "8f14e45f-ceea-4c9b-8b3f-2f1a3d4e5f6a",
  "status": "pending_documents",
  "status_label": "Pending Documents",
  "metadata": [],
  "created_at": "2026-07-19T09:15:00+00:00",
  "updated_at": "2026-07-19T09:16:12+00:00",
  "completed_at": null,
  "history": [
    {
      "uuid": "1a2b3c4d-0000-4000-8000-000000000001",
      "from_status": null,
      "to_status": "created",
      "action": "create",
      "actor_id": 12,
      "reason": "",
      "occurred_at": "2026-07-19T09:15:00+00:00"
    },
    {
      "uuid": "1a2b3c4d-0000-4000-8000-000000000002",
      "from_status": "created",
      "to_status": "pending_documents",
      "action": "request_documents",
      "actor_id": 12,
      "reason": "",
      "occurred_at": "2026-07-19T09:16:12+00:00"
    }
  ]
}
```

## Perform an action

```
POST /wp-json/bizupkeep-workflow/v1/company-registrations/b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab/actions
Content-Type: application/json

{
  "action": "verify_documents",
  "reason": "All CIPC forms reviewed",
  "context": { "documents_verified": true }
}
```

```json
{
  "uuid": "b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab",
  "status": "documents_verified",
  "status_label": "Documents Verified",
  "metadata": { "documents_verified": true },
  "created_at": "2026-07-19T09:15:00+00:00",
  "updated_at": "2026-07-19T09:20:44+00:00",
  "completed_at": null,
  "workflow_type": "company_registration",
  "subject_type": "company",
  "subject_uuid": "8f14e45f-ceea-4c9b-8b3f-2f1a3d4e5f6a"
}
```

## Roll a workflow back

```
POST /wp-json/bizupkeep-workflow/v1/company-registrations/b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab/rollback
Content-Type: application/json

{
  "reason": "Documents verified in error"
}
```

Returns `200 OK` with the instance back at `"status": "pending_documents"`.

## Error: 422 validation failure

Calling `actions` without a required precondition context field:

```
POST /wp-json/bizupkeep-workflow/v1/company-registrations/b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab/actions
Content-Type: application/json

{ "action": "verify_documents" }
```

```
HTTP/1.1 409 Conflict
Content-Type: application/json

{
  "code": "bizupkeep_workflow_conflict",
  "message": "Documents cannot be verified until they have been reviewed and confirmed complete.",
  "data": { "status": 409 }
}
```

(`PreconditionFailedException` from `CompanyRegistrationGuard::guardVerifyDocuments()` maps to `409`, per `CompanyRegistrationController::handle()`.) A genuine shape-validation failure — e.g. omitting `action` entirely — maps to `422` instead:

```
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json

{
  "code": "bizupkeep_workflow_validation_failed",
  "message": "An action is required.",
  "data": { "status": 422, "errors": { "action": "This field is required." } }
}
```

## Error: 404 not found

```
GET /wp-json/bizupkeep-workflow/v1/company-registrations/00000000-0000-0000-0000-000000000000
```

```
HTTP/1.1 404 Not Found
Content-Type: application/json

{
  "code": "bizupkeep_workflow_not_found",
  "message": "Workflow instance \"00000000-0000-0000-0000-000000000000\" was not found.",
  "data": { "status": 404 }
}
```

## Error: 409 conflict (invalid transition)

Attempting `confirm_payment` while still in `created`:

```
HTTP/1.1 409 Conflict
Content-Type: application/json

{
  "code": "bizupkeep_workflow_conflict",
  "message": "Action \"confirm_payment\" cannot be performed while workflow \"company_registration\" is in status \"created\".",
  "data": { "status": 409 }
}
```
