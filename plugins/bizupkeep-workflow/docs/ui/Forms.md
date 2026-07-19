# Forms

**Status: not implemented.** This plugin ships no HTML forms of its own — `WorkflowAdminMenu` is read-only (see `Admin.md`). This is a design brief for the forms a future UI (admin board, client portal) would need to submit against the existing REST API.

## Forms implied by the existing four endpoints

- **Start a Company Registration** — a single-field form (`company_uuid`, likely a searchable company picker rather than a raw UUID field in any real UI) submitting to `POST /company-registrations`. Requires `workflow.manage`.
- **Perform an action** — a form whose available `action` choices are constrained to whatever `WorkflowStateMachine::allowedActions()` currently permits for that instance (see `WorkflowBoard.md`), plus a free-text `reason` field and an action-specific `context` sub-form. Company Registration's guarded actions each need a distinct context field: `verify_documents` needs a `documents_verified` boolean/checkbox confirmation, `confirm_payment` needs a `payment_reference` text field, `approve` needs a `reviewed_by` text field (see `docs/workflows/Company-Registration.md` for the exact precondition each guards). Submits to `POST /company-registrations/{uuid}/actions`. Requires `workflow.transition`.
- **Roll back** — a single required `reason` field, submitting to `POST /company-registrations/{uuid}/rollback`. Requires `workflow.manage`.

## Validation should mirror, not duplicate, server-side rules

Client-side validation (required fields, UUID format) should exist for good UX, but the authoritative validation is always server-side: `StartCompanyRegistrationRequest`/`CompanyRegistrationActionRequest` for shape validation, `CompanyRegistrationGuard` for business-rule preconditions, `WorkflowStateMachine` for structural transition validity. Any future form implementation must treat a 422/409 response from the API as the ground truth, surfacing the server's actual message (see `docs/api/Examples.md`) rather than only relying on client-side checks.

## Open design questions

Whether action-specific context fields should be driven by a declarative per-action schema exposed by the API (none exists today — a client currently has to know, out of band, that `verify_documents` needs `documents_verified`), or whether that knowledge should simply live in the front-end alongside this documentation, is undecided. No form-schema endpoint exists in this plugin today.
