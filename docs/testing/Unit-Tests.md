# Unit Tests

Located under `tests/Unit/`, these exercise single classes' pure logic in isolation, with no database and no WordPress environment beyond the stubs in `tests/bootstrap.php`.

## `tests/Unit/Enums/WorkflowStatusTest.php` (3 tests)

Verifies `WorkflowStatus` enum semantics directly: `test_terminal_statuses_are_reported_correctly` asserts `Archived`/`Cancelled`/`Rejected` are terminal and `Created`/`Processing`/`Completed` are not; `test_only_completed_and_archived_are_successful` asserts `isSuccessful()`'s exact two-case truth table; `test_every_status_has_a_label` loops `WorkflowStatus::cases()` asserting every case returns a non-empty `label()`.

## `tests/Unit/States/WorkflowStateMachineTest.php` (7 tests)

Exercises `WorkflowStateMachine::apply()`/`allowedActions()` against the real `CompanyRegistrationDefinition` (no fake definition — the real one is simple enough to use directly): a declared action moves to its declared target; an unknown action (`'teleport'`) throws `InvalidTransitionException`; a declared action from the wrong source status throws; no action is permitted once terminal (`Archived` + `cancel` throws); `cancel` succeeds from every one of the six cancellable statuses but throws from `Completed`; `allowedActions()` returns exactly the actions valid from a given status (verified against `DocumentsVerified`, which should permit `request_payment`/`cancel` but not `verify_documents`/`approve`); `allowedActions()` returns `[]` once terminal.

## `tests/Unit/Workflows/CompanyRegistrationGuardTest.php` (6 tests)

Exercises `CompanyRegistrationGuard::guard()` directly — the three guarded actions (`verify_documents`, `confirm_payment`, `approve`) each get a passing case and a failing case (missing/falsy `documents_verified`, missing/blank `payment_reference`, missing/blank `reviewed_by`), asserting `PreconditionFailedException` is thrown exactly when the precondition is unmet.

## `tests/Unit/Workflows/CompanyRegistrationServiceTest.php` (4 tests)

Exercises `CompanyRegistrationService` against fake `WorkflowEngineInterface`/`CompanyServiceInterface` collaborators: verifies `performAction()` rejects an action name not in `ALLOWED_ACTIONS` with `ValidationException`, and that operating on a workflow UUID that resolves to a different workflow type raises `WorkflowNotFoundException` via `assertIsCompanyRegistration()` — the cross-workflow-type UUID confusion guard described in `docs/development/Service-Standards.md`.

## What unit tests deliberately do not cover

Persistence (see `Integration-Tests.md`), full multi-step lifecycles (see `Workflow-Tests.md`), and anything HTTP/REST-layer (see `Acceptance-Tests.md` for why that gap exists). Unit tests in this suite are fast, dependency-free, and focused on one class's own logic.
