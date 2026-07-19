# Regression Tests

## There is no separate "regression suite" — the existing 32 tests serve this role

This plugin does not maintain a distinct regression-test tier separate from its Unit/Integration/Workflow tests. Every test under `tests/Unit/`, `tests/Integration/`, and `tests/Workflow/` (32 in total, run together as the single `BizUpKeepWorkflow` PHPUnit testsuite defined in `phpunit.xml`) is, by definition, the regression suite: it is run on every change and is expected to stay green.

## How regressions are actually caught today

1. **`vendor/bin/phpunit`** — all 32 tests, verified passing.
2. **`vendor/bin/phpstan analyse`** — level 6 static analysis against `includes/`, catching a class of regression (type errors, undefined properties/methods, wrong argument counts) before a test would ever need to.
3. **`vendor/bin/phpcs`** — PSR-12 plus WordPress security sniffs, catching a different class of regression (unescaped output, unprepared SQL, missing i18n) that functional tests are not designed to catch.

Together these three tools, all run in this session and confirmed clean/passing, are this plugin's actual regression-prevention mechanism. See `docs/deployment/CI-CD.md` for how they should be wired into continuous integration going forward (no GitHub Actions workflow exists yet for this plugin specifically).

## What a dedicated regression addition looks like in practice

When a bug is found and fixed in this codebase, the expected practice (consistent with how the existing suite is structured) is: add a new `test_*` method to whichever existing test class already covers the affected behaviour (e.g. a newly-discovered edge case in `WorkflowStateMachine::apply()` gets a new method in `WorkflowStateMachineTest`, not a new, separately-run file), reproducing the bug as a failing assertion first, then fixing the source until it passes — keeping the regression permanently encoded in the same suite that already covers that class, rather than accumulating a parallel "bugs we found" test directory.

## Known gaps this regression suite does not close

Because there is no acceptance-test tier (`Acceptance-Tests.md`) and no performance-test tier (`Performance-Tests.md`), a regression in REST-layer behaviour, real WordPress capability integration, or performance under load would not be caught by this suite today — those categories of regression currently rely on manual verification or code review until the corresponding test tiers are built.
