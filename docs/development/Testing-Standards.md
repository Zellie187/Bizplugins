# Testing Standards

## Current state: 32 passing tests, clean PHPStan level 6, clean PHPCS

This is the actual, verified state of this codebase at the time of writing — not a target. See `docs/testing/` for the full breakdown by test category.

## Test suite layout

```
tests/
├── bootstrap.php                              WordPress function stubs
├── Mocks/InMemoryDatabase.php                  fake DatabaseInterface
├── Unit/
│   ├── Enums/WorkflowStatusTest.php            (3 tests)
│   ├── States/WorkflowStateMachineTest.php     (7 tests)
│   └── Workflows/
│       ├── CompanyRegistrationGuardTest.php    (6 tests)
│       └── CompanyRegistrationServiceTest.php  (4 tests)
├── Integration/
│   └── Repositories/WorkflowRepositoryTest.php (5 tests)
├── Workflow/
│   └── CompanyRegistrationWorkflowTest.php     (7 tests)
├── Feature/    (empty, .gitkeep only)
└── Fixtures/   (empty, .gitkeep only)
```

`phpunit.xml` runs the whole `tests/` directory as a single `BizUpKeepWorkflow` testsuite, bootstrapped by `tests/bootstrap.php`.

## The bootstrap: minimal WordPress stubs, deliberately

`tests/bootstrap.php` defines the handful of WordPress functions this codebase's non-Controller layers actually call (`__()`, `add_filter()`/`add_action()`, `apply_filters()`/`do_action()`, `get_option()`/`update_option()`/`delete_option()`, `get_role()`, `user_can()`) as plain PHP functions backed by `$GLOBALS` arrays — not a full WordPress test environment (no `wp-env`, no real `$wpdb`, no plugin loading). This is a deliberate, narrow scope: it exists so the engine's pure business logic is testable in milliseconds, in any CI runner, with no WordPress installation at all.

## No real database — `InMemoryDatabase`

`tests/Mocks/InMemoryDatabase.php implements DatabaseInterface` entirely in PHP arrays (`private array $tables`), supporting equality-only `findAll()` criteria and single-column ordering — sufficient for every repository in this codebase, and deliberately mirroring `BizHub\Tests\Mocks\InMemoryDatabase` in the sibling BizHub plugin so both projects' repository tests behave under identical semantics. See `docs/testing/Integration-Tests.md`.

## What is and is not tested

**Tested**: `WorkflowStatus` enum semantics, `WorkflowStateMachine`'s transition-validity rules, `CompanyRegistrationGuard`'s three preconditions, `CompanyRegistrationService`'s action-allowlisting/type-guarding, `WorkflowRepository` round-tripping against the in-memory fake, and the full Company Registration lifecycle end-to-end (happy path, precondition rejection, arbitrary-transition rejection, cancellation, rollback) through the real `WorkflowManager`.

**Not tested** (see `docs/testing/Acceptance-Tests.md` and `Performance-Tests.md`): the REST Controller layer against real HTTP requests, WordPress capability integration against a real `wp_roles` table, browser/UI acceptance flows, and load/performance characteristics. None of these require a code change to add — they require a WordPress test environment (`wp-env` or similar) this environment did not have available.

## Writing a new test

Match the existing style: one `final class ... extends TestCase` per class under test, `test_snake_case_sentence` method names describing the exact behaviour under test (not `testFoo`), and prefer exercising real collaborators (as `CompanyRegistrationWorkflowTest` does with a real `WorkflowManager` + real `WorkflowRepository` + `InMemoryDatabase`) over mocking, unless the collaborator is WordPress/BizHub infrastructure this plugin does not own.
