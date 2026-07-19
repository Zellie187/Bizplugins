# Roadmap

## Shipped in 1.0.0

- The generic workflow engine (state machine, guards, repository, events, audit trail).
- The **Company Registration** workflow, fully implemented end-to-end (engine → REST API → admin screen).
- A REST API for Company Registration, and a read-only admin list screen.

See `CHANGELOG.md` for the full 1.0.0 entry and `docs/workflows/Company-Registration.md` for the implemented workflow's detail.

## Next milestones: the 15 specified workflow types

`docs/workflows/` contains implementation-ready design specifications (Input/Validation/Preconditions/Business Rules/State Changes/Events/Notifications/Rollback/Completion Criteria/Audit Logging, per `docs/development/Workflow-Standards.md`) for fifteen further South African compliance workflow types, none of which are built yet:

- **CIPC company-record maintenance**: Director Changes, Address Changes, Name Changes, Annual Returns.
- **SARS registrations**: Tax Registration, VAT Registration, PAYE Registration, UIF Registration.
- **Cross-cutting/reusable workflow types**: Client Onboarding, Document Workflow, Payment Workflow, Approval Workflow, Escalation Workflow (plus Notification Workflow, which is scoped as a delivery-tracking question rather than a new workflow type — see its own document).

Each should be implemented following the exact five-piece pattern Company Registration already establishes (`Definition`/`Guard`/`Service`/`Controller`+`Request`/`ServiceProvider`), registered into `bizupkeep-workflow.php`'s `bizhub/register_providers` callback alongside `CompanyRegistrationServiceProvider`, with no changes required to the engine itself.

## Future architecture work

- **Queue system** (`docs/architecture/Queue-System.md`) — `includes/Queues/` is reserved but empty; needed before any asynchronous notification delivery, batch processing, or scheduled work can be built.
- **Automation** (`docs/architecture/Automation-Architecture.md`) — time-based and event-based triggers for automatically advancing or flagging workflows (e.g. overdue Annual Returns, stuck Escalations), most plausibly built on top of the queue system above.
- **Client Portal UI** (`docs/ui/ClientPortal.md`) — a client-facing, client-scoped view of a company's own workflows, distinct from the current staff-only admin screen.
- **Dashboard and Workflow Board UI** (`docs/ui/Dashboard.md`, `docs/ui/WorkflowBoard.md`) — richer staff-facing views beyond the current flat list table.
- **Acceptance and performance test tiers** (`docs/testing/Acceptance-Tests.md`, `docs/testing/Performance-Tests.md`) — currently blocked on having a real WordPress/MySQL test environment available.
- **CI/CD automation** (`docs/deployment/CI-CD.md`) — no GitHub Actions workflow exists yet for this plugin; the sibling BizHub repository's `.github/workflows/php.yml` is the model to adapt.

## Guiding principle for all future work

Every addition should continue to route through BizHub's single shared DI container and event dispatcher (never a second one), and every new workflow type should be enforced structurally by the existing `WorkflowStateMachine`/`TransitionGuardInterface` machinery rather than introducing a parallel, workflow-type-specific validation mechanism.
