# Roadmap

## Shipped in 1.0.0

- The generic workflow engine (state machine, guards, repository, events, audit trail).
- The **Company Registration** workflow, fully implemented end-to-end (engine → REST API → admin screen).
- A REST API for Company Registration, and a read-only admin list screen.
- A **Quality Review** staff admin screen (approve/reject applications sitting in `QualityReview`).

See `CHANGELOG.md` for the full 1.0.0 entry and `docs/workflows/Company-Registration.md` for the implemented workflow's detail.

## Shipped in 1.1.0

- The **Company Amendment** workflow (`CompanyAmendmentDefinition`/`Guard`/`Service`/`ServiceProvider`, type `company_amendment`): a single application covering Director, Name, and/or registered-Address changes in any combination, matching the product catalogue's combined SKUs (Director & Name, Director & Address, Name & Address, All-in-One). **This intentionally departs from `docs/workflows/Director-Changes.md`, `Name-Changes.md`, and `Address-Changes.md` below**, each of which proposed a separate workflow type per amendment kind — a client only ever files one application per amendment request, so this is modelled as one workflow instance whose metadata (`amendment_types`) records which change(s) it covers, with `CompanyAmendmentGuard` enforcing the per-type required data (proposed names for a name change, director add/remove/update entries for a director change, a complete new address for an address change) before documents can be verified. Those three docs remain useful as the source of the CIPC-facing business rules (e.g. "at least one director must remain", "registered-office vs. postal address"), just not as three separate workflow types.
- The **Annual Return** workflow (`AnnualReturnDefinition`/`Guard`/`Service`/`ServiceProvider`, type `annual_return`), implemented per `docs/workflows/Annual-Returns.md`'s proposed lifecycle, including the duplicate-filing check (one non-cancelled Annual Return per company per financial year).
- Client-facing intake for all three workflow types (New Registration / Company Amendment / Annual Return), live on the `astra-child` theme's `/apply/` page. Director records now carry phone/email/residential-address fields too (`BizHub\Companies\Entities\Director`, bizhub 0.2.5), needed by the New Registration director repeater.

## Shipped in 1.2.0

- **Quality Review and the "Workflows" admin list now cover all three workflow types**, closing the gap 1.1.0 left open: Company Amendment and Annual Return applications are reviewable (Quality Review) and visible (Workflows list), not just Company Registration. See `CHANGELOG.md` for detail, including the new `WorkflowTypeServiceInterface` and `WorkflowRepositoryInterface::summariesByStatus()` that made this a small, type-agnostic change rather than three copy-pasted admin screens.

**Still not built for Company Amendment/Annual Return**: REST controllers/routes (the theme's intake calls the services directly via the shared container, the same pattern Company Registration's intake always used). Also still not built for any type: automatically applying an *approved* Company Amendment's requested changes to the live Company/Director records - approval today is only a status transition, same as it's always been for Company Registration.

## Shipped in 1.3.0

- **Company Registration's name-rejection loop**: when CIPC declines every proposed company name, staff now have a second, recoverable rejection path (`reject_name`, distinct from the existing terminal `reject`) that sends the workflow to a new `NamesRejected` status instead of ending it. The client submits new names from their My Applications page, which fires `resubmit_names` and returns the workflow to `QualityReview` - closing the gap Company Registration's original design left open (see `docs/workflows/Company-Registration.md`'s "if name rejected, request new names" requirement, not addressed by 1.0.0's plain terminal Reject). See `CHANGELOG.md` for the full detail.

**Still not built**: the equivalent loop for Company Amendment's name-change branch (an amendment can also propose a new company name that CIPC could decline) - out of scope for this round, which followed the original spec's wording of this requirement under Company Registration specifically.

## Next milestones: the remaining specified workflow types

`docs/workflows/` contains implementation-ready design specifications (Input/Validation/Preconditions/Business Rules/State Changes/Events/Notifications/Rollback/Completion Criteria/Audit Logging, per `docs/development/Workflow-Standards.md`) for further South African compliance workflow types not yet built:

- **SARS registrations**: Tax Registration, VAT Registration, PAYE Registration, UIF Registration.
- **Cross-cutting/reusable workflow types**: Client Onboarding, Document Workflow, Payment Workflow, Approval Workflow, Escalation Workflow (plus Notification Workflow, which is scoped as a delivery-tracking question rather than a new workflow type — see its own document).

Each should be implemented following the same pattern Company Registration, Company Amendment, and Annual Return already establish (`Definition`/`Guard`/`Service`/`ServiceProvider`, plus a `Controller`+`Request` pair once REST access is needed), registered into `bizupkeep-workflow.php`'s `bizhub/register_providers` callback, with no changes required to the engine itself.

## Future architecture work

- **Queue system** (`docs/architecture/Queue-System.md`) — `includes/Queues/` is reserved but empty; needed before any asynchronous notification delivery, batch processing, or scheduled work can be built.
- **Automation** (`docs/architecture/Automation-Architecture.md`) — time-based and event-based triggers for automatically advancing or flagging workflows (e.g. overdue Annual Returns, stuck Escalations), most plausibly built on top of the queue system above.
- **Client Portal UI** (`docs/ui/ClientPortal.md`) — a client-facing, client-scoped view of a company's own workflows, distinct from the current staff-only admin screen.
- **Dashboard and Workflow Board UI** (`docs/ui/Dashboard.md`, `docs/ui/WorkflowBoard.md`) — richer staff-facing views beyond the current flat list table.
- **Acceptance and performance test tiers** (`docs/testing/Acceptance-Tests.md`, `docs/testing/Performance-Tests.md`) — currently blocked on having a real WordPress/MySQL test environment available.
- **CI/CD automation** (`docs/deployment/CI-CD.md`) — no GitHub Actions workflow exists yet for this plugin; the sibling BizHub repository's `.github/workflows/php.yml` is the model to adapt.

## Guiding principle for all future work

Every addition should continue to route through BizHub's single shared DI container and event dispatcher (never a second one), and every new workflow type should be enforced structurally by the existing `WorkflowStateMachine`/`TransitionGuardInterface` machinery rather than introducing a parallel, workflow-type-specific validation mechanism.
