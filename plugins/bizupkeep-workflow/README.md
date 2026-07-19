# BizUpKeep Workflow

Business process automation and workflow engine for the BizUpKeep platform. BizUpKeep Workflow extends the [BizHub](https://bizupkeep.co.za) framework with a workflow-driven, event-driven business process layer, purpose-built for South African company registration and compliance processes.

## Dependencies

BizUpKeep Workflow is not a standalone plugin — it requires both of the following to be active:

- **BizHub** (`bizhub`) — the shared framework plugin providing the DI container, database abstraction, event dispatcher, logging, and authorization service this plugin builds on.
- **BizUpKeep Core** (`bizupkeep-core`) — the platform's primary plugin.

Both are declared via `Requires Plugins: bizhub, bizupkeep-core` in this plugin's header and enforced at runtime by `BizHub\Workflow\Bootstrap\DependencyGuard`, which deactivates this plugin with an admin notice if either is missing.

## What's implemented today

- A generic, reusable workflow engine (`WorkflowEngineInterface`/`WorkflowManager`): create, transition, roll back, and query workflow instances against a declarative state machine, with pluggable per-workflow-type business-rule guards.
- One concrete workflow type: **Company Registration** — a 10-status, 9-action lifecycle from `Created` through `Archived`, with cancellation and rejection paths. See `docs/workflows/Company-Registration.md`.
- A REST API (`bizupkeep-workflow/v1`) exposing Company Registration over four endpoints.
- An admin screen (BizHub → Workflows) listing Company Registration instances.
- 32 passing PHPUnit tests, clean PHPStan level 6, clean PHPCS.

15 additional workflow types (Director Changes, Tax/VAT/PAYE/UIF Registration, Annual Returns, and others) are specified as ready-to-build design documents in `docs/workflows/` but not yet implemented — see `ROADMAP.md`.

## Quick API example

```
POST /wp-json/bizupkeep-workflow/v1/company-registrations
Content-Type: application/json

{ "company_uuid": "8f14e45f-ceea-4c9b-8b3f-2f1a3d4e5f6a" }
```

```json
{
  "uuid": "b6b4b1e0-9c3d-4a2a-8b1c-1234567890ab",
  "workflow_type": "company_registration",
  "status": "created",
  "status_label": "Created",
  "created_at": "2026-07-19T09:15:00+00:00"
}
```

See `docs/api/Examples.md` for the full request/response reference across all four endpoints, and `docs/api/Authentication.md` for how requests are authenticated and authorized.

## Documentation

Full documentation lives under [`docs/`](docs/):

- [`docs/architecture/`](docs/architecture/) — how the engine, database, security, events, and integration with BizHub are built.
- [`docs/development/`](docs/development/) — coding, testing, and design standards (BH-WORKFLOW-SPEC-001).
- [`docs/workflows/`](docs/workflows/) — Company Registration in full detail, plus 15 specified-but-unbuilt workflow types.
- [`docs/database/`](docs/database/), [`docs/api/`](docs/api/), [`docs/ui/`](docs/ui/), [`docs/security/`](docs/security/), [`docs/testing/`](docs/testing/), [`docs/deployment/`](docs/deployment/).

## Installing

See `docs/deployment/Installation.md` for the full sequence. In short: ensure BizHub and BizUpKeep Core are active, install this plugin, and activate it — activation runs the database migration and grants this plugin's capabilities to the appropriate roles automatically.

## Contributing

See `CONTRIBUTING.md` for the development workflow, coding standards, and required checks (`phpcs`, `phpstan`, `phpunit`).
