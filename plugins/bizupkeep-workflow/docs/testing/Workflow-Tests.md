# Workflow Tests

Located under `tests/Workflow/`, this is the suite's end-to-end layer: the real `WorkflowManager`, the real `WorkflowStateMachine`, the real `CompanyRegistrationDefinition`/`CompanyRegistrationGuard`, and the real `WorkflowRepository` (against `InMemoryDatabase`) and `EventDispatcher`, wired together exactly as `Container/definitions.php` would wire them in production — only the WordPress-facing Controller and a real MySQL driver are absent.

## `tests/Workflow/CompanyRegistrationWorkflowTest.php` (7 tests)

- **`test_full_happy_path_reaches_archived`** — drives a single instance through all nine actions in sequence (`request_documents` → `verify_documents` → `request_payment` → `confirm_payment` → `start_quality_review` → `approve` → `archive`), supplying the exact guard-required context at each guarded step (`documents_verified: true`, `payment_reference: 'PMT-1'`, `reviewed_by: 'Jane Reviewer'`), asserting the status after every single step, that `completedAt` is set once `Completed` is reached, that a `WorkflowCompleted` event was dispatched, that the instance is `isTerminal()` once `Archived`, and that its full history contains exactly 7 recorded transitions.
- **`test_verify_documents_is_rejected_without_precondition_context`** — confirms `verify_documents` without `documents_verified` context throws `PreconditionFailedException`, proving the guard runs even when the state machine alone would have permitted the transition.
- **`test_an_arbitrary_transition_is_rejected`** — confirms jumping straight from `Created` to `confirm_payment`'s target throws `InvalidTransitionException`, the "no arbitrary state transitions" guarantee exercised end-to-end.
- **`test_transitioning_an_unknown_workflow_throws_not_found`** — a `TransitionWorkflowCommand` against a UUID that was never created throws `WorkflowNotFoundException`.
- **`test_cancel_moves_a_workflow_to_a_terminal_cancelled_state_and_raises_cancelled_event`** — `cancel` succeeds, the instance is terminal, `WorkflowCancelled` was dispatched, and any further action afterward throws `InvalidTransitionException`.
- **`test_rollback_returns_a_workflow_to_its_previous_status`** — after `request_documents`, `rollback()` returns the instance to `Created` and dispatches `WorkflowRolledBack`.
- **`test_rollback_is_rejected_once_a_workflow_is_terminal`** — `rollback()` on an already-`Cancelled` instance throws `InvalidTransitionException`.

## How events are captured

Rather than mocking the `EventDispatcher`, the test registers plain PHP closures as listeners for all five event classes against the real dispatcher, appending each dispatched event to an array (`$this->dispatchedEvents`) the assertions then inspect — proving event dispatch end-to-end through the real `EventDispatcher`, not a substitute.

## Why this suite exists as a separate tier from Unit tests

This is the only tier that proves the *whole* engine — state machine, guard, repository, and event dispatch working together — produces the correct observable behaviour for a complete, realistic workflow lifecycle, not just that each piece is individually correct in isolation.
