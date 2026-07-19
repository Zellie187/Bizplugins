# Integration Tests

Located under `tests/Integration/`, these exercise a real class against a real collaborator's contract, substituting only infrastructure this plugin does not own (the actual MySQL connection).

## `tests/Integration/Repositories/WorkflowRepositoryTest.php` (5 tests)

Exercises the real `WorkflowRepository` — not a fake or a mock of it — against `tests/Mocks/InMemoryDatabase`, a fake `DatabaseInterface` implementation:

- `test_a_saved_workflow_instance_round_trips_through_find` — a `WorkflowInstance::start()`-created instance survives `save()` then `find()` with all fields intact (UUID, workflow type, status, `created_by`, metadata).
- `test_find_returns_null_for_an_unknown_uuid` — `find()` on a never-saved UUID returns `null`, not an exception.
- `test_recorded_transitions_are_returned_by_history_in_order` — two `recordTransition()` calls come back from `history()` in `occurred_at ASC` order.
- `test_find_hydrates_history_alongside_the_instance` — `find()`'s returned `WorkflowInstance` carries its recorded transitions via `withHistory()`, not just its own row.
- `test_summaries_are_ordered_by_most_recently_updated_first` — seeds two instances directly into the fake's table array (bypassing `save()`, to control `updated_at` precisely) and asserts `summaries()` returns the more-recently-updated one first.

## Why `InMemoryDatabase`, not a real MySQL instance

There is no real database available in this test environment — no `wp-env`, no Docker MySQL container wired into CI. `tests/Mocks/InMemoryDatabase implements DatabaseInterface` entirely with PHP arrays, deliberately mirroring `BizHub\Tests\Mocks\InMemoryDatabase` in the sibling BizHub plugin (same method signatures, same equality-only `findAll()` semantics, same single-column `orderBy` support) so that `WorkflowRepository`'s tests are exercised against the identical fake contract BizHub's own repositories (e.g. `CompanyRepository`) are tested against — no divergent, workflow-specific test-double semantics to keep separately correct.

## What this means for confidence

Because `WorkflowRepositoryInterface` is the seam `WorkflowManager` depends on (see `docs/development/Repository-Standards.md`), and `WorkflowRepository` is exercised here against the *real* interface contract (not a hand-rolled repository stub), these tests give real confidence that the repository's SQL-shaped logic (criteria matching, ordering, insert-vs-update decisions) is correct — the only thing not exercised is the actual SQL dialect a real MySQL server would execute, which is `BizHub\Framework\Database`'s concrete `DatabaseInterface` implementation's responsibility to get right, not this plugin's.
